<?php

namespace App\Services\Migration;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;

class MediaHashService
{
    public function record(?array $hashes, string $field, string $path, string $localFile): array
    {
        $hashes = (array) $hashes;

        if (! is_file($localFile)) {
            unset($hashes[$field]);

            return $hashes;
        }

        $hashes[$field] = [
            'path' => $path,
            'sha256' => hash_file('sha256', $localFile),
            'size' => (int) filesize($localFile),
        ];

        return $hashes;
    }

    public function recordOn(Video $video, string $field, string $path, string $localFile): void
    {
        $video->setAttribute('media_hashes', $this->record($video->getAttribute('media_hashes'), $field, $path, $localFile));
    }

    public function forVideo(Video $video): array
    {
        $hashes = (array) $video->getAttribute('media_hashes');
        $changed = false;

        $paths = [
            'vid_optimized' => $video->has_processed && $video->vid_optimized && ! str_starts_with($video->vid_optimized, 'https://') ? $video->vid_optimized : null,
            'thumbnail' => $this->thumbnailPath($video),
        ];

        foreach ($paths as $field => $path) {
            if (! $path) {
                if (isset($hashes[$field])) {
                    unset($hashes[$field]);
                    $changed = true;
                }

                continue;
            }

            if (($hashes[$field]['path'] ?? null) === $path && ! empty($hashes[$field]['sha256'])) {
                continue;
            }

            $entry = $this->hashStored($path);

            if ($entry) {
                $hashes[$field] = $entry;
                $changed = true;
            }
        }

        if ($changed) {
            $video->forceFill(['media_hashes' => $hashes])->saveQuietly();
        }

        return $hashes;
    }

    public function thumbnailPath(Video $video): ?string
    {
        if (! $video->has_thumb) {
            return null;
        }

        if ($video->thumbnail_path) {
            return $video->thumbnail_path;
        }

        if ($video->thumbnail || ! $video->vid || str_starts_with($video->vid, 'https://')) {
            return null;
        }

        $ext = pathinfo($video->vid, PATHINFO_EXTENSION);

        return $ext ? str_replace('.'.$ext, '.jpg', $video->vid) : null;
    }

    public function hashStored(string $path): ?array
    {
        $disk = Storage::disk('s3');

        try {
            $stream = $disk->readStream($path);
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_resource($stream)) {
            return null;
        }

        $context = hash_init('sha256');
        $size = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1048576);

                if ($chunk === false) {
                    return null;
                }

                hash_update($context, $chunk);
                $size += strlen($chunk);
            }
        } finally {
            fclose($stream);
        }

        if ($size === 0) {
            return null;
        }

        return [
            'path' => $path,
            'sha256' => hash_final($context),
            'size' => $size,
        ];
    }
}
