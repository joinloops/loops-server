<?php

namespace App\Services;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateAccountPassword;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Jobs\Video\VideoOptimizeJob;
use App\Models\User;
use App\Rules\ValidUsername;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ClientConfigService
{
    public const CACHE_KEY = 'loops:services:client-config:v2';

    public const VERSION = '1.0';

    public const REL = 'https://w3id.org/fep/65ca/1.0';

    public const EXTENSION_KEY = 'https://joinloops.org/ns/client-config';

    public const CACHE_TTL_SECONDS = 3600;

    public const ACCOUNT_MAX_AGE_SECONDS = 600;

    protected const VIDEO_MIME_MAP = [
        'mp4' => 'video/mp4',
        'm4v' => 'video/x-m4v',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        'mkv' => 'video/x-matroska',
        'avi' => 'video/x-msvideo',
        '3gp' => 'video/3gpp',
    ];

    protected const IMAGE_MIME_MAP = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function get(bool $flush = false): array
    {
        if ($flush) {
            $this->flush();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->build());
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function accountFromRequest(Request $request): ?User
    {
        if (! $request->bearerToken()) {
            return null;
        }

        try {
            $user = Auth::guard('api')->user();
        } catch (\Throwable) {
            return null;
        }

        return $user instanceof User ? $user : null;
    }

    public function forUser(User $user): array
    {
        $config = $this->get();
        $profile = $user->profile;
        $active = (int) $user->status === 1 && $profile && (int) $profile->status === 1;
        $canPost = $active && (bool) $user->can_upload;

        $config['scope'] = 'account';
        $config['composer']['allowed'] = $canPost;

        if (! $canPost) {
            $config['media']['max_attachments'] = 0;
            $config['media']['min_attachments'] = 0;
            $config['composer']['scheduling']['supported'] = false;
        }

        unset($config['links']['registration']);

        $ext = &$config['extensions'][self::EXTENSION_KEY];
        $ext['comments']['allowed'] = $active && (bool) $user->can_comment;
        $ext['video']['embed_allowed'] = $canPost && (bool) $user->can_embed;
        $ext['profile_links']['account_max_count'] = $profile ? LinkLimitService::getMaxLinks($profile) : 0;
        unset($ext);

        return $config;
    }

    public function discovery(): array
    {
        return [
            'links' => [
                [
                    'rel' => self::REL,
                    'href' => route('client-config.1.0'),
                ],
            ],
        ];
    }

    public function build(): array
    {
        $settings = app(SettingsFileService::class)->getPublicConfig();

        return [
            'version' => self::VERSION,
            'scope' => 'default',
            'account_scope_supported' => true,
            'software' => [
                'name' => 'loops',
                'version' => (string) app('app_version'),
            ],
            'length_unit' => 'code_points',
            'media' => $this->media($settings),
            'composer' => $this->composer(),
            'accounts' => $this->accounts(),
            'links' => $this->links($settings),
            'extensions' => [
                self::EXTENSION_KEY => $this->loopsExtension(),
            ],
        ];
    }

    protected function media(array $settings): array
    {
        $maxVideoMb = (int) data_get($settings, 'media.max_video_size', 40);

        $maxDuration = min(
            (int) data_get($settings, 'media.max_video_duration', VideoOptimizeJob::MAX_DURATION_SECONDS),
            VideoOptimizeJob::MAX_DURATION_SECONDS
        );

        return [
            'max_attachments' => 1,
            'min_attachments' => 1,
            'types' => [
                [
                    'id' => 'video',
                    'mime_types' => $this->mimeTypes(config('loops.media.video_types', ['mp4']), self::VIDEO_MIME_MAP),
                    'max_attachments' => 1,
                    'max_bytes' => $maxVideoMb * 1024 * 1024,
                    'max_duration_seconds' => $maxDuration,
                    'transcodes' => true,
                    'strips_metadata' => true,
                ],
            ],
            'alt_text' => [
                'max_length' => StoreVideoRequest::ALT_TEXT_MAX_LENGTH,
                'required' => false,
            ],
            'focal_point' => false,
        ];
    }

    protected function composer(): array
    {
        return [
            'allowed' => true,
            'max_length' => StoreVideoRequest::CAPTION_MAX_LENGTH,
            'url_weight' => null,
            'mention_counting' => 'full',
            'content_types' => ['text/plain'],
            'content_warning' => [
                'supported' => false,
            ],
            'polls' => [
                'supported' => false,
            ],
            'visibility' => ['public'],
            'editing' => [
                'supported' => true,
                'window_seconds' => null,
                'max_edits' => null,
            ],
            'scheduling' => [
                'supported' => true,
                'min_lead_seconds' => VideoScheduleService::MIN_LEAD_SECONDS,
                'max_lead_seconds' => VideoScheduleService::MAX_HORIZON_DAYS * 86400,
                'max_scheduled' => VideoScheduleService::MAX_PENDING,
            ],
            'languages' => [
                'supported' => array_values(app(IntlService::class)->keys()),
                'required' => false,
            ],
        ];
    }

    protected function accounts(): array
    {
        $changes = app(UsernameChangeService::class);

        return [
            'username' => [
                'min_length' => UsernameChangeService::MIN_LENGTH,
                'max_length' => UsernameChangeService::MAX_LENGTH,
                'pattern' => ValidUsername::ECMASCRIPT_PATTERN,
                'allowed_characters' => ValidUsername::ALLOWED_CHARACTERS,
                'case_insensitive' => true,
                'changeable' => $changes->enabled() && $changes->limit() > 0,
                'change_cooldown_seconds' => $changes->cooldownHours() * 3600,
            ],
            'display_name' => [
                'max_length' => UpdateProfileRequest::NAME_MAX_LENGTH,
            ],
            'bio' => [
                'max_length' => UpdateProfileRequest::BIO_MAX_LENGTH,
            ],
            'avatar' => [
                'mime_types' => $this->mimeTypes(UpdateAvatarRequest::MIMES, self::IMAGE_MIME_MAP),
                'max_bytes' => UpdateAvatarRequest::MAX_KILOBYTES * 1024,
            ],
            'max_pinned_posts' => UpdateVideoRequest::MAX_PINNED_VIDEOS,
            'password' => [
                'min_length' => UpdateAccountPassword::MIN_LENGTH,
                'max_length' => UpdateAccountPassword::MAX_LENGTH,
            ],
            'two_factor' => [
                'methods' => ['totp'],
            ],
            'follow_approval' => false,
            'migration' => [
                'move_in' => false,
            ],
        ];
    }

    protected function links(array $settings): array
    {
        $links = [
            'login' => url('/login'),
            'password_reset' => url('/auth/forgot-password'),
            'account_settings' => url('/dashboard/account'),
            'two_factor_settings' => url('/dashboard/account/security'),
            'account_deletion' => url('/dashboard/account/delete'),
            'data_export' => url('/dashboard/account/data'),
            'about' => url('/about'),
            'rules' => url('/community-guidelines'),
            'terms' => url('/terms'),
            'privacy' => url('/privacy'),
            'support' => url('/contact'),
        ];

        $mode = data_get($settings, 'registration_mode', 'closed');
        $open = (bool) data_get($settings, 'registration', false);

        if ($mode === 'open' && $open) {
            $links = ['registration' => url('/register')] + $links;
        } elseif ($mode === 'curated') {
            $links = ['registration' => url('/auth/curated')] + $links;
        }

        return $links;
    }

    protected function loopsExtension(): array
    {
        $changes = app(UsernameChangeService::class);

        return [
            'video' => [
                'sensitive_flag' => true,
                'ai_label' => true,
                'ad_label' => true,
                'duet_layouts' => ['side-by-side', 'vertical'],
            ],
            'comments' => [
                'max_length' => StoreCommentRequest::COMMENT_MAX_LENGTH,
                'gif_replies' => filled(config('klipy.api_key')),
            ],
            'profile_links' => [
                'baseline_max_count' => LinkLimitService::baselineLimit(),
                'max_count' => LinkLimitService::maxLimit(),
            ],
            'username_changes' => [
                'max_changes' => $changes->limit(),
                'window_seconds' => $changes->windowDays() * 86400,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $extensions
     * @param  array<string, string>  $map
     * @return array<int, string>
     */
    protected function mimeTypes(array $extensions, array $map): array
    {
        $types = [];

        foreach ($extensions as $ext) {
            $ext = strtolower(ltrim(trim((string) $ext), '.'));

            if (isset($map[$ext])) {
                $types[] = $map[$ext];
            }
        }

        return array_values(array_unique($types));
    }
}
