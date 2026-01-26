<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InboxResolverService
{
    /**
     * Get grouped inbox URLs for a profile's remote followers.
     * Groups by shared_inbox_url when available to minimize deliveries.
     *
     * @param  int  $profileId  The profile whose followers to deliver to
     * @return Collection Collection of ['inbox' => string, 'profile_ids' => array]
     */
    public function getFollowerInboxes(int $profileId): Collection
    {
        return DB::table('followers')
            ->where('followers.following_id', $profileId)
            ->where('followers.profile_is_local', false)
            ->join('profiles', 'profiles.id', '=', 'followers.profile_id')
            ->whereNotNull('profiles.inbox_url')
            ->select([
                'profiles.id as profile_id',
                DB::raw('COALESCE(profiles.shared_inbox_url, profiles.inbox_url) as inbox'),
            ])
            ->get()
            ->groupBy('inbox')
            ->map(fn ($group) => [
                'inbox' => $group->first()->inbox,
                'profile_ids' => $group->pluck('profile_id')->toArray(),
            ])
            ->values();
    }

    /**
     * Get inbox URLs for mentioned profiles (remote only).
     *
     * @param  \Illuminate\Support\Collection  $mentions  Collection of Profile models
     * @return Collection Collection of ['inbox' => string, 'profile_ids' => array]
     */
    public function getMentionInboxes($mentions): Collection
    {
        return $mentions
            ->filter(fn ($profile) => ! $profile->is_local && $profile->inbox_url)
            ->groupBy(fn ($profile) => $profile->shared_inbox_url ?? $profile->inbox_url)
            ->map(fn ($group) => [
                'inbox' => $group->first()->shared_inbox_url ?? $group->first()->inbox_url,
                'profile_ids' => $group->pluck('id')->toArray(),
            ])
            ->values();
    }

    /**
     * Get ALL known remote inboxes for broadcast deletions.
     * Used when you need to send Delete activities to everyone who might have a copy.
     * Groups by shared_inbox_url when available to minimize deliveries.
     *
     * @return Collection Collection of ['inbox' => string, 'profile_ids' => array]
     */
    public function getAllKnownInboxes(): Collection
    {
        return DB::table('profiles')
            ->where('local', false)
            ->whereNotNull('inbox_url')
            ->select([
                'id as profile_id',
                DB::raw('COALESCE(shared_inbox_url, inbox_url) as inbox'),
            ])
            ->get()
            ->groupBy('inbox')
            ->map(function ($group) {
                return [
                    'inbox' => $group->first()->inbox,
                    'profile_ids' => $group->pluck('profile_id')->toArray(),
                ];
            })
            ->values();
    }

    /**
     * Get follower inboxes with chunking for large follower counts.
     *
     * @param  callable  $callback  Called with each chunk of inboxes
     */
    public function chunkFollowerInboxes(int $profileId, callable $callback, int $chunkSize = 500): void
    {
        $processedInboxes = collect();

        DB::table('followers')
            ->where('followers.following_id', $profileId)
            ->where('followers.profile_is_local', false)
            ->join('profiles', 'profiles.id', '=', 'followers.profile_id')
            ->whereNotNull('profiles.inbox_url')
            ->select([
                'profiles.id as profile_id',
                DB::raw('COALESCE(profiles.shared_inbox_url, profiles.inbox_url) as inbox'),
            ])
            ->chunkById($chunkSize, function ($followers) use (&$processedInboxes) {
                $grouped = $followers->groupBy('inbox');

                $inboxGroup = [];
                foreach ($grouped as $inboxUrl => $group) {
                    $inboxGroup[$inboxUrl] = [
                        'inbox' => $group->first()->inbox,
                        'profile_ids' => $group->pluck('profile_id')->toArray(),
                    ];
                }

                foreach ($inboxGroup as $inboxUrl => $data) {
                    if ($processedInboxes->has($inboxUrl)) {
                        $existing = $processedInboxes->get($inboxUrl);
                        $existing['profile_ids'] = array_merge(
                            $existing['profile_ids'],
                            $data['profile_ids']
                        );
                        $processedInboxes->put($inboxUrl, $existing);
                    } else {
                        $processedInboxes->put($inboxUrl, $data);
                    }
                }
            }, 'profiles.id', 'profile_id');

        if ($processedInboxes->isNotEmpty()) {
            $callback($processedInboxes->values());
        }
    }

    /**
     * Get all known remote inboxes with chunking, returns flat inbox URLs.
     * Processes in chunks to avoid memory issues on large instances.
     *
     * @param  callable  $callback  Called with Collection of inbox URL strings
     */
    public function chunkAllKnownInboxesFlat(callable $callback, int $chunkSize = 500): void
    {
        $processedInboxes = collect();

        DB::table('profiles')
            ->where('local', false)
            ->whereNotNull('inbox_url')
            ->select([
                'id',
                DB::raw('COALESCE(shared_inbox_url, inbox_url) as inbox'),
            ])
            ->chunkById($chunkSize, function ($profiles) use (&$processedInboxes) {
                $inboxes = $profiles->pluck('inbox')->unique();

                foreach ($inboxes as $inbox) {
                    $processedInboxes[$inbox] = true;
                }
            }, 'id');

        if ($processedInboxes->isNotEmpty()) {
            $callback($processedInboxes->keys());
        }
    }

    /**
     * Get all known remote inboxes with chunking for large instance counts.
     *
     * @param  callable  $callback  Called with each chunk of inboxes
     */
    public function chunkAllKnownInboxes(callable $callback, int $chunkSize = 500): void
    {
        $processedInboxes = collect();

        DB::table('profiles')
            ->where('local', false)
            ->whereNotNull('inbox_url')
            ->select([
                'id as profile_id',
                DB::raw('COALESCE(shared_inbox_url, inbox_url) as inbox'),
            ])
            ->chunkById($chunkSize, function ($profiles) use (&$processedInboxes) {
                $grouped = $profiles->groupBy('inbox');

                $inboxGroup = [];
                foreach ($grouped as $inboxUrl => $group) {
                    $inboxGroup[$inboxUrl] = [
                        'inbox' => $group->first()->inbox,
                        'profile_ids' => $group->pluck('profile_id')->toArray(),
                    ];
                }

                foreach ($inboxGroup as $inboxUrl => $data) {
                    if ($processedInboxes->has($inboxUrl)) {
                        $existing = $processedInboxes->get($inboxUrl);
                        $existing['profile_ids'] = array_merge(
                            $existing['profile_ids'],
                            $data['profile_ids']
                        );
                        $processedInboxes->put($inboxUrl, $existing);
                    } else {
                        $processedInboxes->put($inboxUrl, $data);
                    }
                }
            }, 'profiles.id', 'profile_id');

        if ($processedInboxes->isNotEmpty()) {
            $callback($processedInboxes->values());
        }
    }
}
