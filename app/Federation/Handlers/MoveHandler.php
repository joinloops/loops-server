<?php

namespace App\Federation\Handlers;

use App\Models\Follower;
use App\Models\Profile;
use App\Services\Migration\FollowerMoveService;
use App\Services\Migration\MigrationPeerService;
use Illuminate\Support\Facades\Cache;

class MoveHandler extends BaseHandler
{
    public function handle(array $activity, Profile $actor, ?Profile $target = null)
    {
        $targetUri = is_array($activity['target']) ? $activity['target']['id'] : $activity['target'];

        $destination = $this->verifiedDestination($activity, $actor, $targetUri);

        if (! $destination) {
            return;
        }

        if (! $actor->local && (string) $actor->moved_to_profile_id !== (string) $destination->id) {
            $actor->forceFill([
                'moved_to_profile_id' => $destination->id,
                'moved_at' => now(),
            ])->save();
        }

        if (! $target || ! $target->local) {
            return;
        }

        if (! Follower::where('profile_id', $target->id)->where('following_id', $actor->id)->exists()) {
            return;
        }

        app(FollowerMoveService::class)->moveFollower($target, $actor, $destination, random_int(0, 120));
    }

    protected function verifiedDestination(array $activity, Profile $actor, string $targetUri): ?Profile
    {
        $key = 'loops:migration:move:'.sha1($actor->uri.'|'.$targetUri);

        $destinationId = Cache::get($key);

        if ($destinationId === null) {
            $destinationId = $this->resolveDestination($actor, $targetUri);
            Cache::put($key, $destinationId, $destinationId ? now()->addMinutes(30) : now()->addMinutes(2));
        }

        if (! $destinationId) {
            return null;
        }

        $destination = Profile::find($destinationId);

        return $destination && (int) $destination->status === 1 ? $destination : null;
    }

    protected function resolveDestination(Profile $actor, string $targetUri): string|false
    {
        if ($actor->local) {
            return false;
        }

        $peers = app(MigrationPeerService::class);
        $origin = $peers->freshActor($actor->uri);

        if (! $origin || $peers->actorMovedTo($origin) !== $targetUri) {
            return false;
        }

        if ($this->isLocalObject($targetUri)) {
            $destination = app(Profile::class)->findOrCreateFromUrl($targetUri);

            if (! $destination || ! $destination->local || ! in_array($actor->uri, $destination->aliases(), true)) {
                return false;
            }

            return (string) $destination->id;
        }

        $remote = $peers->freshActor($targetUri);

        if (! $remote || ! in_array($actor->uri, $peers->actorAliases($remote), true)) {
            return false;
        }

        $destination = app(Profile::class)->findOrCreateFromUrl($targetUri, $remote, true);

        return $destination ? (string) $destination->id : false;
    }
}
