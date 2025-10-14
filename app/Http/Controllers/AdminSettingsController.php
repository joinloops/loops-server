<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Middleware\AdminOnlyAccess;
use App\Jobs\Federation\DiscoverInstance;
use App\Models\AdminSetting;
use App\Services\SanitizeService;
use App\Services\SettingsFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Intervention\Image\Laravel\Facades\Image;
use Storage;

class AdminSettingsController extends Controller
{
    use ApiHelpers;

    protected $settingsFileService;

    public function __construct()
    {
        $this->middleware(AdminOnlyAccess::class);
    }

    public function index(): JsonResponse
    {
        $settings = (new SettingsFileService)->getAdminConfig();

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'general' => 'array',
            'branding' => 'array',
            'media' => 'array',
            'federation' => 'array',
            'general.instanceName' => 'required|string|min:2,max:15',
            'general.instanceUrl' => 'sometimes|nullable|url',
            'general.instanceDescription' => 'sometimes|nullable|max:150',
            'general.adminEmail' => 'sometimes|nullable|email:rfc,dns,spoof,strict',
            'general.supportEmail' => 'sometimes|nullable|email:rfc,dns,spoof,strict',
            'general.supportForum' => 'sometimes|nullable|active_url',
            'general.supportFediverseAccount' => 'sometimes|nullable|active_url',
            'general.openRegistration' => 'required|boolean',
            'general.emailVerification' => 'required|accepted',
        ]);

        $handleSubmit = false;
        foreach ($validated as $section => $settings) {
            foreach ($settings as $key => $value) {
                $settingKey = "{$section}.{$key}";
                $isPublic = $this->isPublicSetting($settingKey);
                if ($section == 'general' && $key == 'instanceUrl') {
                    $value = config('app.url');
                }
                if ($section === 'federation' && $key == 'authorizedFetch') {
                    if ($settings['federationMode'] !== 'open') {
                        $value = true;
                    }
                }
                if ($section === 'federation' && $key == 'enableFederation' && $value) {
                    $handleSubmit = true;
                }
                if ($section === 'federation' && $key == 'autoAcceptFollows') {
                    $value = true;
                }
                if ($section === 'federation' && $key == 'allowedInstances') {
                    foreach ($value as $dom) {
                        DiscoverInstance::dispatch('https://'.$dom);
                    }
                }
                AdminSetting::set(
                    $settingKey,
                    $value ?? '',
                    $this->getSettingType($value),
                    $isPublic,
                    $this->getSettingDescription($settingKey)
                );
            }
        }

        (new SettingsFileService)->flush();

        if ($handleSubmit) {
            $this->handleSubmitToServerList();
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }

    protected function isPublicSetting(string $key): bool
    {
        $publicSettings = [
            'general.instanceName',
            'general.instanceUrl',
            'general.instanceDescription',
            'general.openRegistration',
            'general.emailVerification',
            'branding.logo',
            'branding.favicon',
            'branding.primaryColor',
            'branding.secondaryColor',
            'branding.accentColor',
            'branding.customCSS',
            'media.maxVideoSize',
            'media.maxImageSize',
            'media.maxVideoDuration',
            'media.allowedVideoFormats',
            'federation.enableFederation',
            'federation.federationMode',
        ];

        return in_array($key, $publicSettings);
    }

    protected function getSettingType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'number';
        }
        if (is_array($value)) {
            return 'array';
        }

        return 'string';
    }

    protected function getSettingDescription(string $key): ?string
    {
        $descriptions = [
            'general.instanceName' => 'The display name of this Loops instance',
            'general.instanceUrl' => 'The primary URL for this instance',
            'branding.primaryColor' => 'Main brand color used throughout the interface',
        ];

        return $descriptions[$key] ?? null;
    }

    public function deleteLogo(Request $request)
    {
        $files = Storage::disk('public')->allFiles('branding');

        Storage::disk('public')->delete($files);

        AdminSetting::set(
            'branding.logo',
            null,
            'string',
            true,
            'Logo'
        );
        (new SettingsFileService)->flush();

        return $this->data(['logo_url' => url('/nav-logo.png')]);

    }

    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => [
                'required',
                File::types(['jpg', 'png', 'jpeg'])
                    ->min('1kb')
                    ->max('2mb'),
            ],
        ]);

        $files = Storage::disk('public')->allFiles('branding');

        Storage::disk('public')->delete($files);

        $file = $request->file('logo');

        $img = Image::read($file)
            ->cover(500, 500)
            ->toPng(interlaced: true);

        $filename = 'logo'.'_'.time().Str::random(8).'.png';

        $path = 'branding/'.$filename;

        Storage::disk('public')->put($path, (string) $img);

        $url = '/storage/'.$path;
        AdminSetting::set(
            'branding.logo',
            $url,
            'string',
            true,
            'Logo'
        );
        (new SettingsFileService)->flush();

        return $this->data(['logo_url' => $url]);
    }

    public function handleSubmitToServerList()
    {
        if (Storage::disk('local')->exists('fedidb_submit.json')) {
            return;
        }

        if (config('logging.dev_log')) {
            Log::info('Submitting server to global directory', [
                'app_url' => config('app.url'),
            ]);
        }

        $validUrl = app(SanitizeService::class)->url(config('app.url'), true, false);

        if (! $validUrl) {
            if (config('logging.dev_log')) {
                Log::error('Failed to submit server listing, invalid `APP_URL` found. This could mean you have a typo or your domain does not have a valid A/AAAA record.', [
                    'app_url' => config('app.url'),
                ]);
            }

            return;
        }

        $response = Http::retry(3, 100, throw: false)->post('https://api.fedidb.org/v1/servers/submit', [
            'domains' => config('app.url'),
        ]);

        if ($response->successful()) {
            $res = ['domain' => config('app.url'), 'submitted_at' => now()->format('c')];
            Storage::disk('local')->put('fedidb_submit.json', json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }
}
