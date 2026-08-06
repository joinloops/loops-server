<?php

namespace App\Federation\Handlers;

use App\Models\Follower;
use App\Models\FollowRequest;
use App\Models\Profile;
use App\Services\FollowerService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AcceptHandler
{
    /**
     * Handle an incoming Accept activity.
     * This processes the acceptance of a Follow request.
     *
     * @param  array  $activity  The Accept activity
     * @param  Profile  $actor  The remote profile who sent the Accept (person who was followed)
     * @param  Profile|null  $target  The local profile who initiated the follow
     */
    public function handle(array $activity, Profile $actor, ?Profile $target = null): void
    {
        $followActivity = $activity['object'];
        $followId = $followActivity['id'];

        $followRequestMatch = app(SanitizeService::class)->matchUrlTemplate(
            url: $followId,
            templates: ['/ap/users/{profileId}#follows/{followRequestId}'],
            useAppHost: true,
            constraints: ['profileId' => '\d+', 'followRequestId' => '\d+']
        );

        if (! $followRequestMatch || ! isset($followRequestMatch['profileId'], $followRequestMatch['followRequestId'])) {
            Log::warning('Accept handler: Could not parse follow activity ID', [
                'follow_id' => $followId,
            ]);

            return;
        }

        $parsedProfileId = $followRequestMatch['profileId'];
        $parsedFollowId = $followRequestMatch['followRequestId'];

        $followRequest = FollowRequest::where('id', $parsedFollowId)
            ->where('profile_id', $parsedProfileId)
            ->where('following_id', $actor->id)
            ->first();

        if (! $followRequest) {
            Log::warning('Accept handler: Could not find follow request', [
                'follow_id' => $followId,
                'parsed_follow_id' => $parsedFollowId,
                'parsed_profile_id' => $parsedProfileId,
                'actor_id' => $actor->id,
            ]);

            return;
        }

        if (! $target) {
            $target = $followRequest->actor;
        }

        DB::transaction(function () use ($followRequest, $actor, $target) {
            if ($followRequest->following_state !== 4) {
                $followRequest->update([
                    'following_state' => 4,
                ]);

                if (config('logging.dev_log')) {
                    Log::info('Follow request approved', [
                        'follow_request_id' => $followRequest->id,
                        'state' => $followRequest->getState(),
                    ]);
                }
            }

            Follower::updateOrCreate(
                [
                    'profile_id' => $target->id,
                    'following_id' => $actor->id,
                ],
                [
                    'profile_is_local' => (bool) $target->local,
                    'following_is_local' => (bool) $actor->local,
                ]
            );

            FollowerService::refreshAndSync($actor->id, $target->id);
            ShareSheetService::forget($target->id);
        });
    }
}
