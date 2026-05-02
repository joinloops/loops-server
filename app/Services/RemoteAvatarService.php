<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\ProfileAvatar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RemoteAvatarService
{
    protected const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    protected const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    protected const TIMEOUT = 30;

    public function fetchAndStore(Profile $profile): ?ProfileAvatar
    {
        if ($profile->domain === null || $profile->uri === null) {
            return null;
        }

        $remoteUrl = $profile->avatar;

        if (! $remoteUrl) {
            return null;
        }

        $profileAvatar = ProfileAvatar::firstOrNew(['profile_id' => $profile->id]);

        if ($profileAvatar->exists && ! $profileAvatar->shouldRefetch()) {
            $profile->update(['last_fetched_at' => now()]);

            return $profileAvatar;
        }

        try {
            return DB::transaction(function () use ($profile, $profileAvatar, $remoteUrl) {
                return $this->processAvatar($profile, $profileAvatar, $remoteUrl);
            });
        } catch (\Exception $e) {
            Log::error('Failed to fetch remote avatar', [
                'profile_id' => $profile->id,
                'remote_url' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);

            $this->markAsInvalid($profileAvatar, $remoteUrl);

            return null;
        }
    }

    protected function processAvatar(Profile $profile, ProfileAvatar $profileAvatar, string $remoteUrl): ProfileAvatar
    {
        $response = Http::timeout(self::TIMEOUT)
            ->withOptions(['verify' => true])
            ->get($remoteUrl);

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch avatar: HTTP {$response->status()}");
        }

        $contentType = $response->header('Content-Type');
        $mime = $this->normalizeMimeType($contentType);

        if (! in_array($mime, self::ALLOWED_MIMES)) {
            throw new \Exception("Invalid mime type: {$mime}");
        }

        $size = strlen($response->body());
        if ($size > self::MAX_SIZE) {
            throw new \Exception("Avatar too large: {$size} bytes");
        }

        if ($size === 0) {
            throw new \Exception('Avatar is empty');
        }

        if ($profileAvatar->path && Storage::disk('s3')->exists($profileAvatar->path)) {
            Storage::disk('s3')->delete($profileAvatar->path);
        }

        $extension = $this->getExtensionFromMime($mime);
        $filename = strtolower(Str::random(8)).'.'.$extension;
        $path = "avatars/{$profile->id}/{$filename}";

        Storage::disk('s3')->put($path, $response->body(), 'public');

        $profileAvatar->fill([
            'remote_url' => $remoteUrl,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'is_invalid' => false,
            'skip_refetch' => false,
            'last_fetched_at' => now(),
        ]);

        $profileAvatar->save();

        $profile->update(['last_fetched_at' => now()]);

        AvatarService::remote($profile->id, true);

        return $profileAvatar;
    }

    protected function markAsInvalid(ProfileAvatar $profileAvatar, string $remoteUrl): void
    {
        $profileAvatar->fill([
            'remote_url' => $remoteUrl,
            'is_invalid' => true,
            'last_fetched_at' => now(),
        ])->save();
    }

    protected function normalizeMimeType(?string $contentType): string
    {
        if (! $contentType) {
            return 'application/octet-stream';
        }

        return strtolower(explode(';', $contentType)[0]);
    }

    protected function getExtensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    public function refetchStaleAvatars(int $limit = 100): int
    {
        $staleAvatars = ProfileAvatar::with('profile')
            ->where('is_invalid', false)
            ->where('skip_refetch', false)
            ->where(function ($query) {
                $query->whereNull('last_fetched_at')
                    ->orWhere('last_fetched_at', '<', now()->subDays(30));
            })
            ->limit($limit)
            ->get();

        $count = 0;
        foreach ($staleAvatars as $avatar) {
            $profile = $avatar->profile;

            if (! $profile instanceof Profile) {
                continue;
            }

            if ($this->fetchAndStore($profile)) {
                $count++;
            }
        }

        return $count;
    }
}
