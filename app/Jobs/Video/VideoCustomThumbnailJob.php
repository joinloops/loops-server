<?php

namespace App\Jobs\Video;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class VideoCustomThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = 10;

    public function __construct(public Video $video) {}

    public function handle(): void
    {
        if (! $this->video->thumbnail_path) {
            return;
        }

        $disk = Storage::disk('s3');

        try {
            $originalPath = $this->video->thumbnail_path;
            $contents = $disk->get($originalPath);

            if (! $contents) {
                throw new \Exception('Failed to read thumbnail from S3');
            }

            $image = Image::decodeBinary($contents);
            $image->cover(1080, 1920);

            $tempPath = sys_get_temp_dir().'/'.uniqid($this->video->id.'_thumb_').'.webp';
            $image->encodeUsingFormat(
                Format::WEBP,
                quality: 95,
                strip: true,
            )->save($tempPath);

            $pid = $this->video->profile_id;
            $fileName = 'thumb_'.Str::random(8).'.webp';
            $s3Path = 'videos/'.$pid.'/'.$this->video->id.'/'.$fileName;

            $disk->put($s3Path, file_get_contents($tempPath), 'public');

            unlink($tempPath);

            if ($originalPath !== $s3Path) {
                $disk->delete($originalPath);
            }

            $this->video->has_thumb = true;
            $this->video->thumbnail_path = $s3Path;
            $this->video->thumbnail_width = 1080;
            $this->video->thumbnail_height = 1920;
            $this->video->thumbnail_mime = 'image/webp';
            $this->video->save();

        } catch (\Exception $e) {
            if (config('logging.dev_log')) {
                Log::error('Custom thumbnail optimization failed', [
                    'video_id' => $this->video->id,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
