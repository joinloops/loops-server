<?php

namespace App\Federation\Handlers;

use App\Federation\ActivityBuilders\QuoteRequestActivityBuilder;
use App\Jobs\Federation\DiscoverInstance;
use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Profile;
use App\Models\QuoteAuthorization;
use App\Models\Video;
use App\Services\DeliveryService;
use App\Services\HashidService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\Log;

class QuoteRequestHandler
{
    /**
     * Handle an incoming QuoteRequest activity.
     * This processes a request to quote one of our posts.
     *
     * @param  array  $activity  The QuoteRequest activity
     * @param  Profile  $actor  The remote profile requesting to quote
     * @param  Profile|null  $target  The local profile being quoted
     */
    public function handle(array $activity, Profile $actor, ?Profile $target = null): void
    {
        // Ensure we discover the instance if this is a new remote actor
        if (! $actor->local && $actor->uri) {
            DiscoverInstance::dispatch($actor->uri)->onQueue('activitypub-in');
        }

        $quotedObjectUrl = $activity['object'];
        $quotePostUrl = is_array($activity['instrument'])
            ? ($activity['instrument']['id'] ?? null)
            : $activity['instrument'];

        if (! $quotePostUrl) {
            if (config('logging.dev_log')) {
                Log::warning('QuoteRequest handler: Missing quote post URL', [
                    'activity_id' => $activity['id'],
                    'actor' => $actor->uri,
                ]);
            }

            return;
        }

        $quotable = $this->findQuotableByUrl($quotedObjectUrl);

        if (! $quotable) {
            if (config('logging.dev_log')) {
                Log::warning('QuoteRequest handler: Quoted object not found', [
                    'activity_id' => $activity['id'],
                    'quoted_url' => $quotedObjectUrl,
                    'actor' => $actor->uri,
                ]);
            }

            return;
        }

        if (! $target) {
            $target = $quotable->profile;
        }

        if ($this->shouldAutoApproveQuote($quotable, $actor)) {
            $this->approveQuoteRequest($activity, $quotable, $quotePostUrl, $actor);
        } else {
            $this->rejectQuoteRequest($activity, $quotable, $actor);
        }
    }

    /**
     * Find a quotable object by its ActivityPub URL
     */
    protected function findQuotableByUrl(string $url)
    {
        $statusMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $url,
            templates: [
                '/ap/users/{userId}/video/{videoId}',
            ],
            useAppHost: true,
            constraints: ['userId' => '\d+', 'videoId' => '\d+']
        );

        if ($statusMatch && isset($statusMatch['userId'], $statusMatch['videoId'])) {
            return Video::published()->where('visibility', 1)->whereProfileId($statusMatch['userId'])->whereKey($statusMatch['videoId'])->first();
        }

        $videoHashIdMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $url,
            templates: ['/v/{hashId}'],
            useAppHost: true,
            constraints: ['hashId' => '[0-9a-zA-Z_-]{1,11}']
        );

        if ($videoHashIdMatch && isset($videoHashIdMatch['hashId'])) {
            $decodedId = HashidService::safeDecode($videoHashIdMatch['hashId']);
            if ($decodedId !== null) {
                return Video::published()->where('visibility', 1)->whereKey($decodedId)->first();
            }
        }

        $commentMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $url,
            templates: [
                '/ap/users/{userId}/comment/{replyId}',
            ],
            useAppHost: true,
            constraints: ['userId' => '\d+', 'replyId' => '\d+']
        );

        if ($commentMatch && isset($commentMatch['userId'], $commentMatch['replyId'])) {
            return Comment::whereProfileId($commentMatch['userId'])->whereKey($commentMatch['replyId'])->first();
        }

        $commentReplyMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $url,
            templates: [
                '/ap/users/{userId}/reply/{commentReplyId}',
            ],
            useAppHost: true,
            constraints: ['userId' => '\d+', 'commentReplyId' => '\d+']
        );

        if ($commentReplyMatch && isset($commentReplyMatch['userId'], $commentReplyMatch['commentReplyId'])) {
            return CommentReply::whereProfileId($commentReplyMatch['userId'])->whereKey($commentReplyMatch['commentReplyId'])->first();
        }

        return false;
    }

    /**
     * Check if a quote should be auto-approved based on interaction policy
     */
    protected function shouldAutoApproveQuote($quotable, Profile $quoterProfile): bool
    {
        if ($quotable instanceof \App\Models\Video) {
            if ($quotable->visibility != 1 || $quotable->status != 2 || ! $quotable->is_local) {
                return false;
            }

            return true;
        }

        if ($quotable instanceof \App\Models\Comment || $quotable instanceof \App\Models\CommentReply) {
            if ($quotable->visibility != 1 || $quotable->status != 'active' || $quotable->ap_id) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Approve a quote request
     */
    protected function approveQuoteRequest(
        array $activity,
        $quotable,
        string $quotePostUrl,
        Profile $quoterProfile
    ): void {
        // Ensure the quoter profile has an inbox URL for delivery
        if (! $quoterProfile->inbox_url) {
            // Try to re-fetch the actor data to get the inbox URL (bypass cache)
            $actorData = app(\App\Services\ActivityPubService::class)->get($quoterProfile->uri, [], true, true, true, true);

            if ($actorData && isset($actorData['inbox'])) {
                $quoterProfile->inbox_url = $actorData['inbox'];
                $quoterProfile->shared_inbox_url = data_get($actorData, 'endpoints.sharedInbox');
                $quoterProfile->save();

                // Discover the instance since we're seeing it for the first time
                DiscoverInstance::dispatch($quoterProfile->uri)->onQueue('activitypub-in');

            } else {
                if (config('logging.dev_log')) {
                    Log::error('QuoteRequest handler: Failed to fetch inbox_url for quoter', [
                        'quoter_id' => $quoterProfile->id,
                        'quoter_uri' => $quoterProfile->uri,
                    ]);
                }

                return;
            }
        }

        $existing = QuoteAuthorization::where('quote_post_url', $quotePostUrl)
            ->where('quotable_type', get_class($quotable))
            ->where('quotable_id', $quotable->id)
            ->first();

        if ($existing) {
            if (config('logging.dev_log')) {
                Log::info('QuoteRequest handler: Authorization already exists', [
                    'authorization_id' => $existing->id,
                    'quote_post_url' => $quotePostUrl,
                ]);
            }

            return;
        }

        $authorization = QuoteAuthorization::create([
            'quotable_id' => $quotable->id,
            'quotable_type' => get_class($quotable),
            'quote_post_url' => $quotePostUrl,
            'quoted_profile_id' => $quotable->profile_id,
            'quoter_profile_id' => $quoterProfile->id,
        ]);

        $accept = QuoteRequestActivityBuilder::buildAccept(
            $activity,
            $quotable->profile,
            $authorization
        );

        app(DeliveryService::class)->deliverToInbox(
            $quotable->profile,
            $quoterProfile,
            $accept,
        );

        if (config('logging.dev_log')) {
            Log::info('QuoteRequest approved', [
                'authorization_id' => $authorization->id,
                'quote_post_url' => $quotePostUrl,
                'quotable_type' => get_class($quotable),
                'quotable_id' => $quotable->id,
                'quoter' => $quoterProfile->uri,
            ]);
        }
    }

    /**
     * Reject a quote request
     */
    protected function rejectQuoteRequest(array $activity, $quotable, Profile $quoterProfile): void
    {
        // Ensure the quoter profile has an inbox URL for delivery
        if (! $quoterProfile->inbox_url) {
            // Try to re-fetch the actor data to get the inbox URL (bypass cache)
            $actorData = app(\App\Services\ActivityPubService::class)->get($quoterProfile->uri, [], true, true, true, true);
            if ($actorData && isset($actorData['inbox'])) {
                $quoterProfile->inbox_url = $actorData['inbox'];
                $quoterProfile->shared_inbox_url = data_get($actorData, 'endpoints.sharedInbox');
                $quoterProfile->save();

                // Discover the instance since we're seeing it for the first time
                DiscoverInstance::dispatch($quoterProfile->uri)->onQueue('activitypub-in');

            } else {
                if (config('logging.dev_log')) {
                    Log::error('QuoteRequest handler: Failed to fetch inbox_url for quoter (reject)', [
                        'quoter_id' => $quoterProfile->id,
                        'quoter_uri' => $quoterProfile->uri,
                    ]);
                }

                return;
            }
        }

        $reject = QuoteRequestActivityBuilder::buildReject(
            $activity,
            $quotable->profile
        );

        app(DeliveryService::class)->deliverToInbox(
            $quotable->profile,
            $quoterProfile,
            $reject,
        );

        if (config('logging.dev_log')) {
            Log::info('QuoteRequest rejected', [
                'activity_id' => $activity['id'],
                'quote_post_url' => is_array($activity['instrument'])
                    ? ($activity['instrument']['id'] ?? 'unknown')
                    : $activity['instrument'],
                'quotable_type' => get_class($quotable),
                'quotable_id' => $quotable->id,
                'quoter' => $quoterProfile->uri,
            ]);
        }
    }
}
