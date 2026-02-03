<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisService
{
    const CACHE_KEY = 'loops:services:rediscap:';

    /**
     * Force a re-check of Bloom Filter support.
     * Returns false gracefully on any failure.
     */
    public function recheckSupportsBloomFilters(string $connection = 'default'): bool
    {
        try {
            Cache::forget(self::CACHE_KEY.'bf');

            $result = $this->performSupportCheck($connection);

            Cache::forever(self::CACHE_KEY.'bf', $result);

            return $result;
        } catch (Throwable $e) {
            $this->logFailure('recheckSupportsBloomFilters', $e);

            return false;
        }
    }

    /**
     * Check support (cached).
     * Returns false gracefully on any failure.
     */
    public function supportsBloomFilters(string $connection = 'default'): bool
    {
        try {
            return (bool) Cache::rememberForever(self::CACHE_KEY.'bf', function () use ($connection) {
                return $this->performSupportCheck($connection);
            });
        } catch (Throwable $e) {
            $this->logFailure('supportsBloomFilters', $e);

            return false;
        }
    }

    /**
     * Native check using Redis facade.
     * This method can throw exceptions; they are caught by the public methods above.
     */
    protected function performSupportCheck(string $connection): bool
    {
        $redis = Redis::connection($connection);

        try {
            $client = $redis->client();
            $modules = $client->rawCommand('MODULE', 'LIST');

            if (is_array($modules)) {
                foreach ($modules as $module) {
                    $moduleData = is_array($module) ? $module : (array) $module;

                    $flatInfo = array_map('strtolower', \Illuminate\Support\Arr::flatten($moduleData));

                    if (in_array('bf', $flatInfo) || in_array('redisbloom', $flatInfo)) {
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug('Redis MODULE LIST check skipped/failed', ['error' => $e->getMessage()]);
        }

        try {
            $info = $redis->command('COMMAND', ['INFO', 'BF.RESERVE']);

            return ! empty($info) && ! empty($info[0]);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * centralized logging to keep noise out of the main logic
     */
    private function logFailure(string $method, Throwable $e): void
    {
        Log::warning("RedisService: Failed to check Bloom Filter support in {$method}", [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}
