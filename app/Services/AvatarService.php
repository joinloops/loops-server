<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileAvatar;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class AvatarService
{
    const CACHE_KEY = 'api:s:avatar:';

    const CACHE_REMOTE_KEY = 'api:s:avatar:remote:';

    public static function get($id)
    {
        $acct = AccountService::compact($id);

        return $acct['avatar'] ?? url('/storage/avatars/default.jpg');
    }

    public static function remote($id, $clear = false)
    {
        $key = self::CACHE_REMOTE_KEY.$id;

        if ($clear) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addDays(7), function () use ($id) {
            $profileAvatar = ProfileAvatar::whereProfileId($id)->first();
            if (! $profileAvatar || ! $profileAvatar->path) {
                return url('/storage/avatars/default.jpg');
            }

            return Storage::disk('s3')->url($profileAvatar->path);
        });
    }

    public static function updateAvatar(Profile $profile, $avatarFile, ?array $coordinates = null): string
    {
        $profileId = $profile->id;
        try {
            $hashid = HashidService::safeEncode($profileId);

            if (empty($hashid)) {
                throw new Exception('Invalid hashid generated for profile');
            }
        } catch (Exception $e) {
            throw new Exception('Error storing avatar: '.$e->getMessage());
        }

        self::deleteExistingAvatars($hashid, $profile->id);

        $avatarUrl = self::processAndUploadAvatar($avatarFile, $profileId, $hashid, $coordinates);

        $profile->avatar = $avatarUrl;
        $profile->save();

        AccountService::del($profile->id);

        return $avatarUrl;
    }

    public static function deleteAvatar(Profile $profile): string
    {
        $profileId = $profile->id;
        try {
            $hashid = HashidService::safeEncode($profileId);

            if (empty($hashid)) {
                throw new Exception('Invalid hashid generated for profile');
            }
        } catch (Exception $e) {
            throw new Exception('Error deleting avatar: '.$e->getMessage());
        }

        self::deleteExistingAvatars($hashid, $profile->id);

        $profile->avatar = null;
        $profile->save();

        AccountService::del($profile->id);

        return '';
    }

    private static function deleteExistingAvatars($hashid, $profileId): void
    {
        Storage::disk('s3')->deleteDirectory('avatars/'.$hashid);
        Storage::disk('s3')->deleteDirectory('avatars/'.$profileId);
    }

    private static function processAndUploadAvatar($avatarFile, int $profileId, $hashid, ?array $coordinates = null): string
    {
        $key = strtolower(Str::random(5)).random_int(1234, 9999);
        $filename = 'avatars/'.$hashid.'/'.$key.'.webp';

        $processedImagePath = self::processImage($avatarFile, $profileId, $coordinates);

        try {
            Storage::disk('s3')->put(
                $filename,
                file_get_contents($processedImagePath),
                'public'
            );

            return Storage::disk('s3')->url($filename);
        } finally {
            if (file_exists($processedImagePath)) {
                unlink($processedImagePath);
            }
        }
    }

    private static function processImage($avatarFile, int $profileId, ?array $coordinates = null): string
    {
        $tempFileName = 'avatar-'.$profileId.'-'.time().'.webp';
        $tempPath = storage_path('app/avatars-temp/'.$tempFileName);

        $dirPath = storage_path('app/avatars-temp');
        if (! File::isDirectory($dirPath)) {
            File::makeDirectory($dirPath, 0755, true, true);
        }

        if (! is_writable($dirPath)) {
            throw new \RuntimeException("avatars-temp directory is not writable: {$dirPath}");
        }

        $image = Image::decode($avatarFile);

        if ($coordinates && isset($coordinates['left'], $coordinates['top'], $coordinates['width'], $coordinates['height'])) {
            $image->crop(
                width: (int) round($coordinates['width']),
                height: (int) round($coordinates['height']),
                x: (int) round($coordinates['left']),
                y: (int) round($coordinates['top'])
            );

            $image->scaleDown(width: 300, height: 300);
        } else {
            $image->cover(300, 300);
        }

        $image->encodeUsingFormat(Format::WEBP, quality: 95, strip: true)->save($tempPath);

        return $tempPath;
    }
}
