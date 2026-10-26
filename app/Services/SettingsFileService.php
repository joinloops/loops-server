<?php

namespace App\Services;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingsFileService
{
    public function flush()
    {
        $this->syncDefaultSettings();
        app(ConfigService::class)->forYouFeed(true);
        app(ConfigService::class)->federation(true);
        app(ConfigService::class)->federationMode(true);
        app(ConfigService::class)->userSpamDetection(true);
        app(ConfigService::class)->pushNotifications(true);
        app(ConfigService::class)->federationAllowedServers(true);
        app(ConfigService::class)->federationAuthorizedFetch(true);
        app(ConfigService::class)->starterKits(true);
        app(ConfigService::class)->registrationMode(true);
        app(ConfigService::class)->atomFeeds(true);
        app(StarterKitService::class)->getConfig(true);
        app(AdminDashboardService::class)->getConfigData(true);
        $this->generatePublicConfig();
        app(ClientConfigService::class)->flush();
        $this->generateAdminConfig();
    }

    /**
     * Generate public settings file (safe for frontend consumption)
     */
    public function generatePublicConfig(): string
    {
        $settings = AdminSetting::getPublicSettings();

        $config = [
            'app' => [
                'name' => $settings['general.instanceName'] ?? config('app.name'),
                'url' => config('app.url'),
                'description' => $settings['general.instanceDescription'] ?? '',
            ],
            'branding' => [
                'logo' => $settings['branding.logo'] ?? '/nav-logo.png',
                'favicon' => $settings['branding.favicon'] ?? '/favicon.png',
                'customCSS' => $settings['branding.customCSS'] ?? false,
            ],
            'media' => [
                'max_video_size' => $settings['media.maxVideoSize'] ?? 100,
                'max_video_duration' => $settings['media.maxVideoDuration'] ?? 180,
                'allowed_video_formats' => $settings['media.allowedVideoFormats'] ?? ['mp4'],
            ],
            'fyf' => $settings['fyf.enabled'] ?? false,
            'registration' => $settings['general.openRegistration'] ?? false,
            'registration_mode' => $settings['general.registration_mode'] ?? 'closed',
            'federation' => $settings['federation.enableFederation'] ?? false,
            'pushNotifications' => $settings['general.pushNotifications'] ?? false,
            'starterKits' => $settings['starterKits.enabled'] ?? false,
            'atomFeeds' => $settings['general.userAtomFeeds'] ?? false,
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Cache::put('settings:public', $config);

        return $json;
    }

    /**
     * Generate admin settings file (includes sensitive data)
     */
    public function generateAdminConfig(): string
    {
        $settings = AdminSetting::getAllSettings();

        $config = $settings;

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Cache::put('settings:admin', $config);

        return $json;
    }

    /**
     * Get public config (from cache, file, or DB fallback)
     */
    public function getPublicConfig(): array
    {
        $config = Cache::get('settings:public');
        if ($config) {
            return $config;
        }

        return json_decode($this->generatePublicConfig(), true);
    }

    /**
     * Get admin config (from cache or DB fallback)
     */
    public function getAdminConfig(): array
    {
        return json_decode($this->generateAdminConfig(), true);
    }

    /**
     * Get CDN URL for public config
     */
    public function getPublicConfigUrl(): string
    {
        return Storage::disk('public')->url('config/app-config.json');
    }

    public function syncDefaultSettings()
    {
        $defaultSettings = $this->defaultSettings();

        foreach ($defaultSettings as $setting) {
            if (AdminSetting::where('key', $setting['key'])->exists()) {
                continue;
            }
            AdminSetting::create([
                'key' => $setting['key'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'is_public' => $setting['is_public'],
                'description' => $setting['description'],
                'version' => 1,
            ]);
        }
    }

    public function defaultSettings(): array
    {
        return [
            [
                'key' => 'general.instanceName',
                'value' => 'My Loops Instance',
                'type' => 'string',
                'is_public' => true,
                'description' => 'The display name of this Loops instance',
            ],
            [
                'key' => 'general.instanceUrl',
                'value' => config('app.url'),
                'type' => 'string',
                'is_public' => true,
                'description' => 'The primary URL for this instance',
            ],
            [
                'key' => 'general.instanceDescription',
                'value' => 'A creative community for sharing short videos and connecting with others.',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Description shown on the instance homepage',
            ],
            [
                'key' => 'general.adminEmail',
                'value' => 'admin@example.com',
                'type' => 'string',
                'is_public' => false,
                'description' => 'Primary administrator email address',
            ],
            [
                'key' => 'general.supportEmail',
                'value' => 'support@example.com',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Support contact email address',
            ],
            [
                'key' => 'general.supportForum',
                'value' => 'https://github.com/joinloops/loops-server/discussions',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Support forum',
            ],
            [
                'key' => 'general.supportFediverseAccount',
                'value' => null,
                'type' => 'string',
                'is_public' => true,
                'description' => 'Support forum',
            ],
            [
                'key' => 'general.openRegistration',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
                'description' => 'Allow new users to register without approval',
            ],
            [
                'key' => 'general.emailVerification',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
                'description' => 'Require email verification for new accounts',
            ],
            [
                'key' => 'general.userSpamDetection',
                'value' => false,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Check ip/email against known spammer database',
            ],
            [
                'key' => 'general.pushNotifications',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
                'description' => 'Enable push notifications support',
            ],
            [
                'key' => 'general.defaultContentStatus',
                'value' => 'published',
                'type' => 'string',
                'is_public' => false,
                'description' => 'Default status for new content uploads',
            ],
            [
                'key' => 'general.autoModerateNSFW',
                'value' => true,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Automatically flag potentially NSFW content for review',
            ],
            [
                'key' => 'branding.logo',
                'value' => null,
                'type' => 'string',
                'is_public' => true,
                'description' => 'Instance logo image URL',
            ],
            [
                'key' => 'branding.favicon',
                'value' => null,
                'type' => 'string',
                'is_public' => true,
                'description' => 'Instance favicon URL',
            ],
            [
                'key' => 'branding.primaryColor',
                'value' => '#3b82f6',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Primary brand color',
            ],
            [
                'key' => 'branding.secondaryColor',
                'value' => '#8b5cf6',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Secondary brand color',
            ],
            [
                'key' => 'branding.accentColor',
                'value' => '#10b981',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Accent brand color',
            ],
            [
                'key' => 'branding.customCSS',
                'value' => '',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Custom CSS to be applied to the frontend',
            ],

            // Media Settings
            [
                'key' => 'media.maxVideoSize',
                'value' => 40,
                'type' => 'number',
                'is_public' => true,
                'description' => 'Maximum video file size in MB',
            ],
            [
                'key' => 'media.maxImageSize',
                'value' => 10,
                'type' => 'number',
                'is_public' => true,
                'description' => 'Maximum image file size in MB',
            ],
            [
                'key' => 'media.maxVideoDuration',
                'value' => 60,
                'type' => 'number',
                'is_public' => true,
                'description' => 'Maximum video duration in seconds',
            ],
            [
                'key' => 'media.allowedVideoFormats',
                'value' => ['mp4'],
                'type' => 'array',
                'is_public' => true,
                'description' => 'Allowed video file formats',
            ],
            [
                'key' => 'media.storageDriver',
                'value' => 'local',
                'type' => 'string',
                'is_public' => false,
                'description' => 'Storage driver for media files',
            ],
            [
                'key' => 'media.bucketName',
                'value' => '',
                'type' => 'string',
                'is_public' => false,
                'description' => 'Cloud storage bucket name',
            ],
            [
                'key' => 'media.cdnUrl',
                'value' => '',
                'type' => 'string',
                'is_public' => false,
                'description' => 'CDN URL for media files',
            ],
            [
                'key' => 'media.autoThumbnails',
                'value' => true,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Automatically generate thumbnails for videos',
            ],
            [
                'key' => 'media.videoTranscoding',
                'value' => false,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Enable video transcoding for optimization',
            ],
            [
                'key' => 'federation.enableFederation',
                'value' => false,
                'type' => 'boolean',
                'is_public' => true,
                'description' => 'Enable ActivityPub federation',
            ],
            [
                'key' => 'federation.federationMode',
                'value' => 'open',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Federation mode: open, allowlist, or blocklist',
            ],
            [
                'key' => 'federation.allowedInstances',
                'value' => [],
                'type' => 'array',
                'is_public' => false,
                'description' => 'List of allowed federated instances',
            ],
            [
                'key' => 'federation.blockedInstances',
                'value' => [],
                'type' => 'array',
                'is_public' => false,
                'description' => 'List of blocked federated instances',
            ],
            [
                'key' => 'federation.autoAcceptFollows',
                'value' => true,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Automatically accept follow requests from federated users',
            ],
            [
                'key' => 'federation.authorizedFetch',
                'value' => false,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Enforce http signatures on actor/activity endpoints',
            ],
            [
                'key' => 'federation.rateLimit',
                'value' => 1000,
                'type' => 'number',
                'is_public' => false,
                'description' => 'Federation rate limit in requests per hour',
            ],

            // Starter Kits
            [
                'key' => 'starterKits.enabled',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
                'description' => 'Enable Starter Kits feature',
            ],
            [
                'key' => 'starterKits.minFollowersToCreate',
                'value' => 100,
                'type' => 'number',
                'is_public' => false,
                'description' => 'Min # of followers an account needs to create a new Starter Kit',
            ],
            [
                'key' => 'starterKits.maxKitsPerAccount',
                'value' => 5,
                'type' => 'number',
                'is_public' => false,
                'description' => 'Max # of Starter Kits allowed per account',
            ],
            [
                'key' => 'starterKits.kitCreationRequiresAdminApproval',
                'value' => true,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Admins must approve new Kits before they go live',
            ],
            [
                'key' => 'starterKits.allowAutoApproveKitsByCreatorsWithMinFollowers',
                'value' => false,
                'type' => 'boolean',
                'is_public' => false,
                'description' => 'Allow Auto approve Starter Kits created by accounts with that many followers',
            ],
            [
                'key' => 'starterKits.autoApproveKitsByCreatorsWithMinFollowers',
                'value' => 1000,
                'type' => 'number',
                'is_public' => false,
                'description' => 'Auto approve Starter Kits created by accounts with this many followers or more',
            ],
            [
                'key' => 'general.registration_mode',
                'value' => 'open',
                'type' => 'string',
                'is_public' => true,
                'description' => 'Registration mode: open, curated, closed',
            ],

            // Feeds
            [
                'key' => 'general.userAtomFeeds',
                'value' => true,
                'type' => 'boolean',
                'is_public' => true,
                'description' => 'Allow users to enable atom feeds of the most recent 20 public videos',
            ],
        ];
    }
}
