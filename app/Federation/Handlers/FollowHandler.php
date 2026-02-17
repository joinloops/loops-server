<?php

namespace App\Federation\Handlers;

use App\Jobs\Federation\DeliverAcceptActivity;
use App\Models\Follower;
use App\Models\Profile;
use App\Models\UserFilter;
use App\Services\NotificationService;
use App\Services\SanitizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FollowHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, Profile $target)
    {
        $targetUrl = $activity['object'];

        try {
            DB::beginTransaction();

            $targetProfile = $this->findLocalProfile($targetUrl);

            if (! $targetProfile) {
                throw new \Exception("Target profile not found: {$targetUrl}");
            }

            $targetIsBlocking = UserFilter::whereProfileId($targetProfile->id)->whereAccountId($actor->id)->exists();

            if ($targetIsBlocking) {
                if (config('logging.dev_log')) {
                    Log::info('Target is blocking actor', [
                        'actor' => $actor->username,
                        'target' => $targetProfile->username,
                    ]);
                }
                DB::commit();

                return;
            }

            $existingFollow = Follower::where('profile_id', $actor->id)
                ->where('following_id', $targetProfile->id)
                ->first();

            if ($existingFollow) {
                if (config('logging.dev_log')) {
                    Log::info('Follow already exists', [
                        'actor' => $actor->username,
                        'target' => $targetProfile->username,
                    ]);
                }
                $follower = $this->createFollowRelationship($actor, $targetProfile, $activity);
                $this->sendAcceptActivity($activity, $targetProfile, $actor, $follower);
                DB::commit();

                return $existingFollow;
            }

            $follower = $this->createFollowRelationship($actor, $targetProfile, $activity);

            $this->sendAcceptActivity($activity, $targetProfile, $actor, $follower);

            DB::commit();

            NotificationService::newFollower($targetProfile->id, $actor->id);

            if (config('logging.dev_log')) {
                Log::info('Successfully handled Follow activity', [
                    'actor' => $actor->username,
                    'target' => $targetProfile->username,
                    'activity_id' => $activity['id'] ?? 'unknown',
                ]);
            }

            return $follower;

        } catch (\Exception $e) {
            DB::rollBack();

            if (config('logging.dev_log')) {
                Log::error('Failed to handle Follow activity', [
                    'actor' => $actor->username,
                    'target' => $targetUrl,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            throw $e;
        }
    }

    private function findLocalProfile(string $url): ?Profile
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
                    return Profile::whereLocal(true)->whereKey($profileMatch['profileId'])->first();
                }

                if (isset($profileMatch['username'])) {
                    return Profile::where('username', $profileMatch['username'])->whereLocal(true)->first();
                }
            }
        }

        return Profile::where('uri', $url)->whereLocal(true)->first();
    }

    private function createFollowRelationship(Profile $actor, Profile $target, array $activity): Follower
    {
        $follower = Follower::firstOrCreate([
            'profile_id' => $actor->id,
            'following_id' => $target->id,
        ], [
            'profile_is_local' => false,
            'following_is_local' => true,
        ]);

        return $follower;
    }

    private function sendAcceptActivity(array $originalActivity, Profile $target, Profile $actor, Follower $follower): void
    {
        $newObject = [
            'id' => $originalActivity['id'],
            'type' => 'Follow',
            'actor' => $originalActivity['actor'],
            'object' => $originalActivity['object'],
        ];

        $acceptActivity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => config('app.url').'/ap/users/'.$follower->following_id.'#accepts/follows/'.$follower->id,
            'type' => 'Accept',
            'actor' => $target->getActorId(),
            'object' => $newObject,
        ];

        DeliverAcceptActivity::dispatch($acceptActivity, $target, $actor)->onQueue('activitypub-out');
    }
}
