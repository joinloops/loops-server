<?php

namespace App\Services\Migration;

use App\Federation\Migration;
use App\Models\AdminSetting;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Cache;

class MigrationSettings
{
    public const CACHE_KEY = 'loops:migration:settings';

    public const DEFAULTS = [
        'outbound_enabled' => true,
        'inbound_enabled' => true,
        'allow_over_limits' => false,
        'cooldown_days' => 30,
        'transit_urls' => [],
        'require_seal' => false,
    ];

    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return AdminSetting::where('key', 'like', 'migration.%')
                ->get()
                ->mapWithKeys(fn ($row) => [substr($row->key, strlen('migration.')) => $row->value])
                ->all();
        });

        $settings = array_merge(self::DEFAULTS, array_intersect_key((array) $stored, self::DEFAULTS));

        $settings['outbound_enabled'] = (bool) $settings['outbound_enabled'];
        $settings['inbound_enabled'] = (bool) $settings['inbound_enabled'];
        $settings['allow_over_limits'] = (bool) $settings['allow_over_limits'];
        $settings['require_seal'] = (bool) $settings['require_seal'];
        $settings['cooldown_days'] = max(0, min(365, (int) $settings['cooldown_days']));
        $settings['transit_urls'] = array_values(array_filter((array) $settings['transit_urls'], 'is_string'));

        return $settings;
    }

    public function update(array $values): array
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (array_key_exists($key, $values)) {
                AdminSetting::set('migration.'.$key, $values[$key], is_array($default) ? 'array' : (is_bool($default) ? 'boolean' : 'integer'));
            }
        }

        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    public function federationEnabled(): bool
    {
        return (bool) app(ConfigService::class)->federation();
    }

    public function outboundEnabled(): bool
    {
        return $this->federationEnabled() && $this->all()['outbound_enabled'];
    }

    public function inboundEnabled(): bool
    {
        return $this->federationEnabled() && $this->all()['inbound_enabled'];
    }

    public function allowOverLimits(): bool
    {
        return $this->all()['allow_over_limits'];
    }

    public function cooldownDays(): int
    {
        return $this->all()['cooldown_days'];
    }

    public function transitUrls(): array
    {
        return $this->all()['transit_urls'];
    }

    public function primaryTransit(): ?string
    {
        return $this->transitUrls()[0] ?? null;
    }

    public function trustsTransit(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $normalized = rtrim($url, '/');

        foreach ($this->transitUrls() as $trusted) {
            if (rtrim($trusted, '/') === $normalized) {
                return true;
            }
        }

        return false;
    }

    public function requireSeal(): bool
    {
        return $this->all()['require_seal'];
    }

    public function maxVideoBytes(): int
    {
        $settings = Cache::get('settings:public');
        $localMb = (int) data_get($settings, 'media.max_video_size', 40);
        $hardMb = (int) config('loops.migration.hard_max_video_mb', 512);

        $mb = $this->allowOverLimits() ? $hardMb : min($localMb, $hardMb);

        return max(1, $mb) * 1024 * 1024;
    }

    public function maxDuration(): int
    {
        $settings = Cache::get('settings:public');
        $local = (int) data_get($settings, 'media.max_video_duration', 180);
        $hard = (int) config('loops.migration.hard_max_duration', 900);

        return $this->allowOverLimits() ? $hard : min($local, $hard);
    }

    public function publicSummary(): array
    {
        return [
            'version' => Migration::VERSION,
            'inbound' => $this->inboundEnabled(),
            'outbound' => $this->outboundEnabled(),
            'transit' => $this->transitUrls(),
        ];
    }
}
