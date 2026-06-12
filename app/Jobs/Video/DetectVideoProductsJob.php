<?php

namespace App\Jobs\Video;

use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DetectVideoProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;

    public $timeout = 120;

    public $tries = 2;

    public $maxExceptions = 2;

    public $deleteWhenMissingModels = true;

    public function __construct($video)
    {
        $this->video = $video->withoutRelations();
        $this->onQueue('video-processing');
    }

    public function handle(): void
    {
        $video = $this->video->fresh();

        if (! $video) {
            Log::warning('Video not found for product detection', ['video_id' => $this->video->id]);

            return;
        }

        if (! $video->thumbnail_path || ! $video->has_thumb) {
            Log::info('No thumbnail available for product detection', ['video_id' => $video->id]);

            return;
        }

        $aiServiceUrl = config('services.loops_ai.url', 'http://loops-ai:8500');
        if (! $aiServiceUrl) {
            Log::info('AI commerce service not configured, skipping product detection');

            return;
        }

        try {
            // Download thumbnail from S3 to temp file
            $localThumb = tempnam(sys_get_temp_dir(), 'prod_detect_').'.jpg';
            $thumbContent = Storage::disk('s3')->get($video->thumbnail_path);
            file_put_contents($localThumb, $thumbContent);

            // Send to AI service for product detection
            $response = Http::timeout(30)
                ->attach('file', file_get_contents($localThumb), basename($video->thumbnail_path))
                ->post("{$aiServiceUrl}/ai/detect-products");

            @unlink($localThumb);

            if (! $response->successful()) {
                Log::warning('AI product detection failed', [
                    'video_id' => $video->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $result = $response->json();
            $products = $result['products'] ?? [];

            if (empty($products)) {
                Log::info('No products detected in video', ['video_id' => $video->id]);

                return;
            }

            foreach ($products as $detected) {
                $name = $detected['name'] ?? '';
                if (empty($name)) {
                    continue;
                }

                // Find or create product by name
                $product = Product::firstOrCreate(
                    ['name' => $name, 'profile_id' => $video->profile_id],
                    [
                        'profile_id' => $video->profile_id,
                        'name' => $name,
                        'description' => "Auto-detected from video #{$video->id}",
                        'price' => 0,
                        'currency' => 'USD',
                        'category' => $detected['category'] ?? 'other',
                        'tags' => ['ai-detected', $detected['category'] ?? ''],
                        'stock' => 0,
                        'status' => 'active',
                    ]
                );

                // Link product to video
                $bbox = $detected['bounding_box'] ?? [];
                ProductVideo::create([
                    'product_id' => $product->id,
                    'video_id' => $video->id,
                    'bounding_box_x' => $bbox['x'] ?? null,
                    'bounding_box_y' => $bbox['y'] ?? null,
                    'bounding_box_w' => $bbox['w'] ?? null,
                    'bounding_box_h' => $bbox['h'] ?? null,
                    'detection_method' => 'ai_auto',
                    'confidence' => $detected['confidence'] ?? null,
                ]);

                Log::info('Product auto-detected and linked', [
                    'video_id' => $video->id,
                    'product_id' => $product->id,
                    'product_name' => $name,
                    'confidence' => $detected['confidence'] ?? null,
                ]);
            }

            Log::info('Product detection completed', [
                'video_id' => $video->id,
                'products_detected' => count($products),
            ]);
        } catch (\Exception $e) {
            Log::error('Product detection failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Product detection job permanently failed', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
