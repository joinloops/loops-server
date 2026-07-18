<?php

namespace App\Jobs\StarterKit;

use App\Models\StarterKit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Alignment;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class FetchRemoteStarterKitMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    const MAX_DOWNLOAD_SIZE = 5 * 1024 * 1024;

    const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    const MEDIA_CONFIG = [
        'icon' => [
            'max_width' => 400,
            'max_height' => 400,
            'aspect_ratio' => [1, 1],
        ],
        'header' => [
            'max_width' => 1500,
            'max_height' => 600,
            'aspect_ratio' => [5, 2],
        ],
    ];

    public function __construct(
        protected int $kitId
    ) {}

    public function handle(): void
    {
        $kit = StarterKit::find($this->kitId);

        if (! $kit || $kit->is_local) {
            return;
        }

        if ($kit->remote_icon_url && ! $kit->icon_path) {
            $this->fetchAndStore($kit, 'icon');
        }

        if ($kit->remote_header_url && ! $kit->header_path) {
            $this->fetchAndStore($kit, 'header');
        }
    }

    protected function fetchAndStore(StarterKit $kit, string $type): void
    {
        $url = $kit->{"remote_{$type}_url"};

        if (! $url) {
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(['stream' => true])
                ->get($url);

            if (! $response->successful()) {
                Log::debug("Failed to fetch remote starter kit {$type}", [
                    'kit_id' => $kit->id,
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return;
            }

            $contentLength = (int) $response->header('Content-Length');

            if ($contentLength > self::MAX_DOWNLOAD_SIZE) {
                Log::debug("Remote starter kit {$type} exceeds max size", [
                    'kit_id' => $kit->id,
                    'size' => $contentLength,
                ]);

                return;
            }

            $mime = $response->header('Content-Type');

            if (! in_array($mime, self::ALLOWED_MIMES)) {
                Log::debug("Remote starter kit {$type} has unsupported mime", [
                    'kit_id' => $kit->id,
                    'mime' => $mime,
                ]);

                return;
            }

            $body = $response->body();

            if (strlen($body) > self::MAX_DOWNLOAD_SIZE) {
                return;
            }

            $config = self::MEDIA_CONFIG[$type];
            $image = Image::decode($body);

            $image = $this->cropToAspectRatio(
                $image,
                $config['aspect_ratio'][0],
                $config['aspect_ratio'][1]
            );

            $image = $image->scaleDown(
                width: $config['max_width'],
                height: $config['max_height']
            );

            $encoded = $image->encodeUsingFormat(Format::WEBP, quality: 85);

            $filename = "{$type}-".Str::random(14).'.webp';
            $path = "starterkit/{$kit->id}/{$filename}";

            Storage::disk('s3')->put($path, (string) $encoded, [
                'ContentType' => 'image/webp',
                'CacheControl' => 'max-age=86400',
                'visibility' => 'public',
            ]);

            /** @phpstan-ignore argument.type */
            $kit->update([
                "{$type}_path" => $path,
                "{$type}_url" => Storage::disk('s3')->url($path),
            ]);
        } catch (\Exception $e) {
            Log::debug("Exception fetching remote starter kit {$type}", [
                'kit_id' => $kit->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function cropToAspectRatio($image, int $ratioW, int $ratioH)
    {
        $width = $image->width();
        $height = $image->height();

        $targetHeight = (int) ($width * $ratioH / $ratioW);

        if ($targetHeight > $height) {
            $targetWidth = (int) ($height * $ratioW / $ratioH);

            return $image->crop($targetWidth, $height, alignment: Alignment::CENTER);
        }

        if ($targetHeight < $height) {
            return $image->crop($width, $targetHeight, alignment: Alignment::CENTER);
        }

        return $image;
    }
}
