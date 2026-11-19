<?php

namespace App\Services\Migration;

use App\Services\Media\VideoTranscoder;
use GuzzleHttp\Psr7\StreamWrapper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class MediaTransferService
{
    public function __construct(
        protected MigrationSettings $settings,
        protected VideoTranscoder $transcoder,
    ) {}

    public function streamToStorage(string $url, string $path, int $maxBytes, ?string $expectedSha256, ?int $expectedSize, string $contentType): array
    {
        $this->assertPublicUrl($url);

        if ($expectedSize !== null && $expectedSize > $maxBytes) {
            throw new MigrationException('Media exceeds the allowed size', 'over_limits');
        }

        $timeout = max(120, (int) config('loops.migration.job_timeout', 900) - 90);

        try {
            $response = Http::withOptions([
                'stream' => true,
                'allow_redirects' => false,
                'connect_timeout' => 10,
            ])
                ->timeout($timeout)
                ->withHeaders(['User-Agent' => app('user_agent')])
                ->get($url);
        } catch (\Throwable $e) {
            throw new MigrationException('Media download failed: '.$e->getMessage(), 'media_unavailable');
        }

        if (! $response->successful()) {
            throw new MigrationException('Media download failed with HTTP '.$response->status(), 'media_unavailable');
        }

        $declared = (int) $response->header('Content-Length');

        if ($declared > $maxBytes) {
            throw new MigrationException('Media exceeds the allowed size', 'over_limits');
        }

        $stream = new HashingStream($response->toPsrResponse()->getBody(), $maxBytes);
        $resource = StreamWrapper::getResource($stream);
        $disk = Storage::disk('s3');

        try {
            $stored = $disk->writeStream($path, $resource, [
                'visibility' => 'public',
                'ContentType' => $contentType,
            ]);
        } catch (MigrationException $e) {
            $disk->delete($path);

            throw $e;
        } catch (\Throwable $e) {
            $disk->delete($path);

            throw new MigrationException('Unable to store media: '.$e->getMessage(), 'storage');
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }

        if (! $stored) {
            throw new MigrationException('Unable to store media', 'storage');
        }

        $sha256 = $stream->digest();
        $size = $stream->bytes();

        if (($expectedSha256 && ! hash_equals(strtolower($expectedSha256), $sha256)) || ($expectedSize !== null && $expectedSize !== $size)) {
            $disk->delete($path);

            throw new MigrationException('Media integrity check failed', 'hash_mismatch');
        }

        return [
            'path' => $path,
            'sha256' => $sha256,
            'size' => $size,
        ];
    }

    public function fetchBytes(string $url, int $maxBytes, ?string $expectedSha256 = null): string
    {
        $this->assertPublicUrl($url);

        try {
            $response = Http::withOptions(['stream' => true, 'allow_redirects' => false, 'connect_timeout' => 10])
                ->timeout(60)
                ->withHeaders(['User-Agent' => app('user_agent')])
                ->get($url);
        } catch (\Throwable $e) {
            throw new MigrationException('Download failed: '.$e->getMessage(), 'media_unavailable');
        }

        if (! $response->successful()) {
            throw new MigrationException('Download failed with HTTP '.$response->status(), 'media_unavailable');
        }

        $stream = new HashingStream($response->toPsrResponse()->getBody(), $maxBytes);
        $contents = '';

        while (! $stream->eof()) {
            $contents .= $stream->read(65536);
        }

        if ($expectedSha256 && ! hash_equals(strtolower($expectedSha256), $stream->digest())) {
            throw new MigrationException('Media integrity check failed', 'hash_mismatch');
        }

        return $contents;
    }

    public function storeImage(string $bytes, string $path, int $maxWidth, int $maxHeight): array
    {
        $temp = tempnam(sys_get_temp_dir(), 'loops-migration-');

        try {
            $image = Image::decodeBinary($bytes);
            $image->scaleDown(width: $maxWidth, height: $maxHeight);
            $image->encodeUsingFormat(Format::JPEG, quality: 85, strip: true)->save($temp);
            $encoded = (string) file_get_contents($temp);
        } catch (\Throwable $e) {
            throw new MigrationException('Image could not be decoded', 'invalid_media');
        } finally {
            @unlink($temp);
        }

        if ($encoded === '') {
            throw new MigrationException('Image could not be decoded', 'invalid_media');
        }

        if (! Storage::disk('s3')->put($path, $encoded, ['visibility' => 'public', 'ContentType' => 'image/jpeg'])) {
            throw new MigrationException('Unable to store image', 'storage');
        }

        return [
            'path' => $path,
            'sha256' => hash('sha256', $encoded),
            'size' => strlen($encoded),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    public function writeTemp(string $bytes, string $extension): string
    {
        $dir = storage_path('app/private/migration-tmp');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/'.bin2hex(random_bytes(12)).'.'.$extension;
        file_put_contents($path, $bytes);

        return $path;
    }

    public function inspectStoredVideo(string $path): array
    {
        try {
            $probe = $this->transcoder->probe($this->probeSource($path), 90);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'probe_failed'];
        }

        $containers = array_map('trim', explode(',', strtolower((string) ($probe['format']['format_name'] ?? ''))));

        if (! array_intersect($containers, (array) config('loops.migration.containers', ['mp4']))) {
            return ['ok' => false, 'reason' => 'container'];
        }

        $video = [];
        $audio = [];

        foreach ($probe['streams'] as $stream) {
            $type = $stream['codec_type'] ?? null;

            if ($type === 'video') {
                $video[] = $stream;
            } elseif ($type === 'audio') {
                $audio[] = $stream;
            } else {
                return ['ok' => false, 'reason' => 'unexpected_stream'];
            }
        }

        if (count($video) !== 1 || ! in_array(strtolower((string) ($video[0]['codec_name'] ?? '')), (array) config('loops.migration.video_codecs', ['h264']), true)) {
            return ['ok' => false, 'reason' => 'video_codec'];
        }

        foreach ($audio as $stream) {
            if (! in_array(strtolower((string) ($stream['codec_name'] ?? '')), (array) config('loops.migration.audio_codecs', ['aac']), true)) {
                return ['ok' => false, 'reason' => 'audio_codec'];
            }
        }

        $width = (int) ($video[0]['width'] ?? 0);
        $height = (int) ($video[0]['height'] ?? 0);
        $maxDimension = (int) config('loops.migration.max_dimension', 4096);

        if ($width < 16 || $height < 16 || $width > $maxDimension || $height > $maxDimension) {
            return ['ok' => false, 'reason' => 'dimensions'];
        }

        $duration = (float) ($probe['format']['duration'] ?? $video[0]['duration'] ?? 0);

        if ($duration <= 0 || $duration > $this->settings->maxDuration() + 1) {
            return ['ok' => false, 'reason' => 'duration'];
        }

        return [
            'ok' => true,
            'width' => $width,
            'height' => $height,
            'duration' => max(1, (int) round($duration)),
            'has_audio' => count($audio) > 0,
        ];
    }

    protected function probeSource(string $path): string
    {
        $disk = Storage::disk('s3');

        try {
            return $disk->temporaryUrl($path, now()->addMinutes(15));
        } catch (\Throwable $e) {
            return $disk->url($path);
        }
    }

    public function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! $parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new MigrationException('Media URL is not allowed', 'invalid_media');
        }

        $host = $parts['host'];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];
        }

        if ($ips === []) {
            throw new MigrationException('Media host could not be resolved', 'media_unavailable');
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new MigrationException('Media URL resolves to a private address', 'invalid_media');
            }
        }
    }
}
