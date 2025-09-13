<?php

namespace App\Services;

use App\Models\Profile;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class AvatarService
{
    const CACHE_KEY = 'api:s:avatar:';

    public static function get($id)
    {
        $acct = AccountService::compact($id);

        return $acct['avatar'] ?? url('/storage/avatars/default.jpg');
    }

    public static function updateAvatar(Profile $profile, $avatarFile): string
    {
        $profileId = $profile->id;
        try {
            $hashid = HashidService::safeEncode($profileId);
        } catch (Exception $e) {
            throw new Exception('Error storing avatar');
        }

        self::deleteExistingAvatars($hashid);

        $avatarUrl = self::processAndUploadAvatar($avatarFile, $profileId, $hashid);

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
        } catch (Exception $e) {
            throw new Exception('Error storing avatar');
        }

        self::deleteExistingAvatars($hashid);

        $profile->avatar = null;
        $profile->save();

        AccountService::del($profile->id);

        return '';
    }

    private static function deleteExistingAvatars($hashid): void
    {
        Storage::disk('s3')->deleteDirectory('avatars/'.$hashid);
    }

    private static function processAndUploadAvatar($avatarFile, int $profileId, $hashid): string
    {
        $key = strtolower(Str::random(5)).random_int(1234, 9999);
        $filename = 'avatars/'.$hashid.'/'.$key.'.jpg';

        $processedImagePath = self::processImage($avatarFile, $profileId);

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

    private static function processImage($avatarFile, int $profileId): string
    {
        $tempFileName = 'avatar-'.$profileId.'-'.time().'.jpg';
        $tempPath = storage_path('app/avatars-temp/'.$tempFileName);

        Storage::disk('local')->makeDirectory('avatars-temp');

        Image::read($avatarFile)
            ->cover(300, 300)
            ->toJpeg(85)
            ->save($tempPath);

        return $tempPath;
    }
}
