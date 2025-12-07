<?php

namespace App\Jobs\Federation;

use App\Models\Instance;
use App\Services\NodeinfoCrawlerService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DiscoverInstance implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public $url;

    public $tries = 3;

    public $timeout = 120;

    public $maxExceptions = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 10, 15];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        $domain = $this->extractDomain($this->url);

        return 'discover_instance:'.strtolower($domain ?? 'invalid');
    }

    /**
     * Determine how long the job should remain unique (in seconds).
     */
    public function uniqueFor(): int
    {
        return 3600;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $domain = $this->extractDomain($this->url);

        if (! $domain || ! $this->isValidDomain($domain)) {
            $this->logDebug('Invalid domain provided', ['url' => $this->url]);

            return;
        }

        $domain = strtolower($domain);

        // Check cache first to avoid DB query
        $cacheKey = "instance_checked:{$domain}";
        if (Cache::has($cacheKey)) {
            $this->logDebug('Instance recently checked (cached), skipping', ['domain' => $domain]);

            return;
        }

        // Check DB if not in cache
        $existingInstance = Instance::where('domain', $domain)
            ->where('version_last_checked_at', '>', now()->subHours(24))
            ->first();

        if ($existingInstance) {
            // Cache the fact that we checked this recently
            Cache::put($cacheKey, true, now()->addHours(24));
            $this->logDebug('Instance recently checked, skipping', ['domain' => $domain]);

            return;
        }

        try {
            $this->logDebug('Starting instance discovery', ['domain' => $domain]);

            // Attempt to fetch nodeinfo
            $versionData = null;
            $hasNodeinfo = false;

            try {
                $versionData = app(NodeinfoCrawlerService::class)->getSoftware($domain);
                $hasNodeinfo = true;
            } catch (\Exception $e) {
                $this->logDebug('Nodeinfo not available for instance', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
            }

            // Always create/update the instance record, even without nodeinfo
            $updateData = [
                'version_last_checked_at' => now(),
                'last_contacted_at' => now(),
                'failure_count' => 0, // Reset failure count since domain exists
                'last_failure_at' => null,
            ];

            // Add nodeinfo data if available
            if ($hasNodeinfo && $versionData) {
                $updateData['software'] = $versionData['name'] ?? null;
                $updateData['version'] = $versionData['version'] ?? null;
            }

            $instance = Instance::updateOrCreate(
                ['domain' => $domain],
                $updateData
            );

            // Cache successful check
            Cache::put($cacheKey, true, now()->addHours(24));

            $this->logDebug('Instance discovery completed', [
                'domain' => $domain,
                'has_nodeinfo' => $hasNodeinfo,
                'software' => $versionData['name'] ?? 'unknown',
                'version' => $versionData['version'] ?? 'unknown',
                'was_created' => $instance->wasRecentlyCreated,
            ]);

        } catch (\Exception $e) {
            // Only fail if we can't even verify the domain exists
            $this->logDebug('Failed to discover instance', [
                'domain' => $domain,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Update failure tracking in instance record
            $this->recordFailure($domain);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $domain = $this->extractDomain($this->url);

        if ($domain) {
            $this->recordFailure(strtolower($domain), true);
        }

        $this->logDebug('Instance discovery job failed permanently', [
            'domain' => $domain ? strtolower($domain) : 'unknown',
            'url' => $this->url,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Extract domain from URL with error handling
     */
    protected function extractDomain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $domain = parse_url($url, PHP_URL_HOST);

        return $domain ?: null;
    }

    /**
     * Validate domain format
     */
    protected function isValidDomain(string $domain): bool
    {
        return preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $domain) === 1;
    }

    /**
     * Record failure in instance record
     */
    protected function recordFailure(string $domain, bool $permanent = false): void
    {
        try {
            $instance = Instance::firstOrCreate(['domain' => $domain]);

            $instance->increment('failure_count');
            $instance->last_failure_at = now();

            if ($permanent) {
                // Optionally mark instance as problematic after permanent failure
                $instance->version_last_checked_at = now();
            }

            $instance->save();
        } catch (\Exception $e) {
            // Fail silently to avoid recursive errors
            $this->logDebug('Failed to record instance failure', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Centralized debug logging
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if (config('logging.dev_log')) {
            Log::info($message, $context);
        }
    }
}
