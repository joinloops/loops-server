<?php

namespace App\Federation\Handlers;

use App\Models\Comment;
use App\Models\CommentCaptionEdit;
use App\Models\CommentReply;
use App\Models\CommentReplyCaptionEdit;
use App\Models\Profile;
use App\Models\Video;
use App\Services\SanitizeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $object = $activity['object'];

        if (! is_array($object) || ! isset($object['type'])) {
            throw new \Exception('Invalid Update object format');
        }

        try {
            DB::beginTransaction();

            $result = match ($object['type']) {
                'Note', 'Article', 'Video', 'Image' => $this->handleStatusUpdate($activity, $actor, $object),
                'Person' => $this->handleProfileUpdate($activity, $actor, $object),
                default => $this->handleGenericUpdate($activity, $actor, $object)
            };

            DB::commit();

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Update activity', [
                    'actor' => $actor->username,
                    'update_type' => $object['type'],
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Update activity', [
                    'actor' => $actor->username,
                    'update_type' => $object['type'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    private function handleStatusUpdate(array $activity, Profile $actor, array $object): bool
    {
        $objectId = $object['id'];
        $status = $this->findLocalStatus($objectId);

        if (! $status) {
            if (config('logging.dev_log')) {
                Log::warning('Update activity - status not found locally', [
                    'actor' => $actor->username,
                    'object_id' => $objectId,
                ]);
            }

            return false;
        }

        $statusClass = get_class($status);

        if ((string) $status->profile_id !== (string) $actor->id) {
            if (config('logging.dev_log')) {
                Log::warning('Update activity rejected - actor not authorized', [
                    'actor' => $actor->username,
                ]);
            }

            return false;
        }

        $updateData = [];

        if (isset($object['content'])) {
            $updateData['caption'] = app(SanitizeService::class)->cleanHtmlWithSpacing($object['content']);
        }

        if (isset($object['summary']) && $statusClass === 'App\Models\Video') {
            $updateData['cw_body'] = $object['summary'];
        }

        if (isset($object['sensitive'])) {
            $updateData['is_sensitive'] = (bool) $object['sensitive'];
        }

        if (isset($object['updated'])) {
            $updateData['updated_at'] = Carbon::parse($object['updated']);
        } else {
            $updateData['updated_at'] = now();
        }

        if ($statusClass === 'App\Models\Comment') {
            $updateData['is_edited'] = true;

            CommentCaptionEdit::create([
                'comment_id' => $status->id,
                'profile_id' => $status->profile_id,
                'caption' => $status->caption,
            ]);
        } elseif ($statusClass === 'App\Models\CommentReply') {
            $updateData['is_edited'] = true;

            CommentReplyCaptionEdit::create([
                'comment_id' => $status->id,
                'profile_id' => $status->profile_id,
                'caption' => $status->caption,
            ]);
        }

        $status->update($updateData);
        $status->syncHashtagsFromCaption();
        $status->syncMentionsFromCaption();

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Status Update', [
                'actor' => $actor->username,
                'status_id' => $status->id,
            ]);
        }

        return true;
    }

    private function handleProfileUpdate(array $activity, Profile $actor, array $object): bool
    {
        $objectId = $object['id'];
        $profile = $this->findLocalProfile($objectId);

        if (! $profile) {
            if (config('logging.dev_log')) {
                Log::warning('Update activity - profile not found locally', [
                    'actor' => $actor->username,
                    'object_id' => $objectId,
                ]);
            }

            return false;
        }

        // Verify the actor owns the profile or is authorized to update it
        if ((string) $profile->id !== (string) $actor->id) {
            if (config('logging.dev_log')) {
                Log::warning('Update activity rejected - actor not authorized for profile update', [
                    'actor' => $actor->username,
                    'profile_id' => $profile->id,
                ]);
            }

            return false;
        }

        // Update profile fields
        $updateData = [];

        if (isset($object['name'])) {
            $updateData['name'] = $object['name'];
        }

        if (isset($object['summary'])) {
            $updateData['bio'] = strip_tags($object['summary']);
        }

        if (isset($object['icon']['url'])) {
            $updateData['avatar'] = $object['icon']['url'];
        }

        if (isset($object['image']['url'])) {
            $updateData['header_bg'] = $object['image']['url'];
        }

        if (isset($object['url'])) {
            $updateData['remote_url'] = app(SanitizeService::class)->url($object['url']);
        }

        if (isset($object['updated'])) {
            $updateData['updated_at'] = Carbon::parse($object['updated']);
        } else {
            $updateData['updated_at'] = now();
        }

        $profile->update($updateData);

        if (config('logging.dev_log')) {
            Log::info('Successfully processed Profile Update', [
                'actor' => $actor->username,
                'profile_id' => $profile->id,
            ]);
        }

        return true;
    }

    private function handleGenericUpdate(array $activity, Profile $actor, array $object): bool
    {
        if (config('logging.dev_log')) {
            Log::info('Generic Update activity - type not specifically handled', [
                'actor' => $actor->username,
                'object_type' => $object['type'],
            ]);
        }

        return true;
    }

    private function findLocalStatus(string $url): Video|Comment|CommentReply|false
    {
        $isLocal = $this->isLocalObject($url);

        if ($isLocal) {
            $statusMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{userId}/video/{videoId}',
                ],
                useAppHost: true,
                constraints: ['userId' => '\d+', 'videoId' => '\d+']
            );

            if ($statusMatch && isset($statusMatch['userId'], $statusMatch['videoId'])) {
                return Video::whereProfileId($statusMatch['userId'])->whereKey($statusMatch['videoId'])->first();
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
        } else {
            $commentMatch = Comment::where('ap_id', $url)->first();
            if ($commentMatch) {
                return $commentMatch;
            }

            $commentReplyMatch = CommentReply::where('ap_id', $url)->first();
            if ($commentReplyMatch) {
                return $commentReplyMatch;
            }

            $statusMatch = Video::where('uri', $url)->first();
            if ($statusMatch) {
                return $statusMatch;
            }
        }

        return false;
    }

    private function findLocalProfile(string $url): Profile|false
    {
        $isLocal = $this->isLocalObject($url);

        if ($isLocal) {
            $profileMatch = app(SanitizeService::class)->matchUrlTemplate(
                url: $url,
                templates: [
                    '/ap/users/{profileId}',
                    '/@{username}',
                    '/users/{username}',
                ],
                useAppHost: true,
                constraints: ['profileId' => '\d+', 'username' => '[a-zA-Z0-9_.-]+']
            );

            if ($profileMatch) {
                if (isset($profileMatch['profileId'])) {
                    return Profile::find($profileMatch['profileId']);
                }

                if (isset($profileMatch['username'])) {
                    return Profile::where('username', $profileMatch['username'])->first();
                }
            }

            return false;
        }

        return Profile::where('uri', $url)->first();
    }
}
