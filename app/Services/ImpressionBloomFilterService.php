<?php

namespace App\Services;

use App\Models\FeedImpression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ImpressionBloomFilterService
{
    private const FILTER_KEY_PREFIX = 'loops:bf:impressions:';

    private const ERROR_RATE = 0.01;

    private const EXPECTED_ITEMS = 1000;

    private const TTL_SECONDS = 1209600;

    private const HYDRATION_DAYS = 90;

    private const MEXISTS_CHUNK_SIZE = 500;

    private const SERVED_KEY_PREFIX = 'loops:bf:served:';

    private const SERVED_EXPECTED_ITEMS = 2000;

    private const SERVED_TTL_SECONDS = 172800;

    public function add(int $profileId, int $videoId): void
    {
        $key = $this->getKey($profileId);
        $this->ensureFilterExists($key);
        $this->bloom('BF.ADD', [$key, (string) $videoId]);
    }

    public function mightContain(int $profileId, int $videoId): bool
    {
        $key = $this->getKey($profileId);

        $this->ensureFilterHydrated($profileId, $key);

        if (! $this->keyExists($key)) {
            return false;
        }

        return (int) $this->bloom('BF.EXISTS', [$key, (string) $videoId]) === 1;
    }

    public function filterVideos(int $profileId, Collection $videos): Collection
    {
        if ($videos->isEmpty()) {
            return $videos;
        }

        $unseen = array_flip($this->filterIds($profileId, $videos->pluck('id')->all()));

        return $videos->filter(
            fn ($video) => isset($unseen[(int) $video->id])
        )->values();
    }

    public function filterIds(int $profileId, array $videoIds): array
    {
        $videoIds = array_values(array_map('intval', $videoIds));

        if (empty($videoIds)) {
            return [];
        }

        $key = $this->getKey($profileId);
        $this->ensureFilterHydrated($profileId, $key);

        if (! $this->keyExists($key)) {
            return $videoIds;
        }

        $unseen = [];

        foreach (array_chunk($videoIds, self::MEXISTS_CHUNK_SIZE) as $chunk) {
            $results = $this->bloom('BF.MEXISTS', array_merge([$key], array_map('strval', $chunk)));

            foreach ($chunk as $i => $videoId) {
                if ((int) ($results[$i] ?? 0) !== 1) {
                    $unseen[] = $videoId;
                }
            }
        }

        return $unseen;
    }

    public function markServed(int $profileId, array $videoIds): void
    {
        if (empty($videoIds)) {
            return;
        }

        $key = self::SERVED_KEY_PREFIX.$profileId;
        $this->ensureFilterExists($key, self::SERVED_EXPECTED_ITEMS, self::SERVED_TTL_SECONDS);
        $this->bloom('BF.MADD', array_merge([$key], array_map('strval', array_values($videoIds))));
    }

    public function servedIds(int $profileId, array $videoIds): array
    {
        $videoIds = array_values(array_map('intval', $videoIds));
        $key = self::SERVED_KEY_PREFIX.$profileId;

        if (empty($videoIds) || ! $this->keyExists($key)) {
            return [];
        }

        $served = [];

        foreach (array_chunk($videoIds, self::MEXISTS_CHUNK_SIZE) as $chunk) {
            $results = $this->bloom('BF.MEXISTS', array_merge([$key], array_map('strval', $chunk)));

            foreach ($chunk as $i => $videoId) {
                if ((int) ($results[$i] ?? 0) === 1) {
                    $served[] = $videoId;
                }
            }
        }

        return $served;
    }

    private function getClient()
    {
        return Redis::connection()->client();
    }

    private function keyExists(string $key): bool
    {
        return (int) $this->getClient()->rawCommand('EXISTS', $key) === 1;
    }

    private function setex(string $key, int $ttl, string $value): void
    {
        $this->getClient()->rawCommand('SETEX', $key, $ttl, $value);
    }

    private function expire(string $key, int $ttl): void
    {
        $this->getClient()->rawCommand('EXPIRE', $key, $ttl);
    }

    private function ensureFilterHydrated(int $profileId, string $key): void
    {
        $hydratedKey = $key.':hydrated';

        if ($this->keyExists($hydratedKey)) {
            return;
        }

        $videoIds = FeedImpression::where('profile_id', $profileId)
            ->where('viewed_at', '>=', now()->subDays(self::HYDRATION_DAYS))
            ->pluck('video_id')
            ->toArray();

        if (empty($videoIds)) {
            $this->setex($hydratedKey, self::TTL_SECONDS, '1');

            return;
        }

        $this->ensureFilterExists($key);

        foreach (array_chunk($videoIds, 100) as $chunk) {
            $this->bloom('BF.MADD', array_merge([$key], array_map('strval', $chunk)));
        }

        if ($this->keyExists($key)) {
            $this->setex($hydratedKey, self::TTL_SECONDS, '1');
        }
    }

    private function ensureFilterExists(
        string $key,
        int $expectedItems = self::EXPECTED_ITEMS,
        int $ttl = self::TTL_SECONDS
    ): void {
        if ($this->keyExists($key)) {
            return;
        }

        try {
            $this->bloom('BF.RESERVE', [$key, self::ERROR_RATE, $expectedItems]);
            $this->expire($key, $ttl);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'exists')) {
                Log::error("BF.RESERVE failed for key {$key}: {$e->getMessage()}");
            }
        }
    }

    private function bloom(string $command, array $args = [])
    {
        return $this->getClient()->rawCommand($command, ...$args);
    }

    private function getKey(int $profileId): string
    {
        $week = now()->format('Y-W');

        return self::FILTER_KEY_PREFIX."{$profileId}:{$week}";
    }

    public function invalidate(int $profileId): void
    {
        $key = $this->getKey($profileId);
        $client = $this->getClient();
        $client->rawCommand('DEL', $key);
        $client->rawCommand('DEL', $key.':hydrated');
        $client->rawCommand('DEL', self::SERVED_KEY_PREFIX.$profileId);
    }
}
