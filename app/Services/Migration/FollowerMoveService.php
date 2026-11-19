<?php

namespace App\Services\Migration;

use App\Jobs\Federation\DeliverFollowRequest;
use App\Models\Follower;
use App\Models\FollowRequest;
use App\Models\Profile;
use App\Services\AccountService;
use App\Services\ShareSheetService;
use App\Services\UserFilterService;
use Illuminate\Support\Facades\DB;

class FollowerMoveService
{
    public function moveFollower(Profile $follower, Profile $from, Profile $to, int $delaySeconds = 0): bool
    {
        if (! $follower->local || (int) $follower->status !== 1) {
            return false;
        }

        if ((string) $follower->id === (string) $to->id || (string) $follower->id === (string) $from->id) {
            return false;
        }

        if (UserFilterService::isBlocking($follower->id, $to->id)) {
            Follower::where('profile_id', $follower->id)->where('following_id', $from->id)->delete();

            return false;
        }

        $followRequest = DB::transaction(function () use ($follower, $from, $to) {
            Follower::where('profile_id', $follower->id)->where('following_id', $from->id)->delete();

            if (Follower::where('profile_id', $follower->id)->where('following_id', $to->id)->exists()) {
                return;
            }

            if ($to->local) {
                Follower::updateOrCreate([
                    'profile_id' => $follower->id,
                    'following_id' => $to->id,
                ], [
                    'following_is_local' => true,
                ]);

                return;
            }

            FollowRequest::where('profile_id', $follower->id)->where('following_id', $to->id)->delete();

            return FollowRequest::create([
                'profile_id' => $follower->id,
                'following_id' => $to->id,
                'profile_is_local' => true,
                'following_is_local' => false,
                'following_state' => 0,
            ]);
        });

        if ($followRequest) {
            $job = DeliverFollowRequest::dispatch($followRequest)->onQueue('activitypub-out');

            if ($delaySeconds > 0) {
                $job->delay(now()->addSeconds($delaySeconds));
            }
        }

        AccountService::del($follower->id);
        ShareSheetService::forget($follower->id);

        return true;
    }
}
