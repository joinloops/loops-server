<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDuetRequest;
use App\Jobs\Video\CompositeDuetVideo;
use App\Jobs\Video\VideoDuetNotification;
use App\Jobs\Video\VideoThumbnailJob;
use App\Models\Profile;
use App\Models\Video;
use App\Services\ConfigService;
use App\Services\FederationDispatcher;
use App\Services\SanitizeService;
use App\Services\UserActivityService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DuetController extends Controller
{
    use ApiHelpers;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDuetRequest $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', '300');

        $pid = $request->user()->profile_id;
        app(UserActivityService::class)->markActive($request->user());
        $profile = Profile::findOrFail($pid);
        $videoFile = $request->file('video');

        $videoMeta = [
            'size' => ceil($videoFile->getSize() / 1024),
            'name' => $videoFile->getClientOriginalName(),
            'mime' => $videoFile->getMimeType(),
        ];

        $model = null;
        $s3Path = null;

        $originalDuet = Video::published()->where('can_duet', true)->findOrFail($request->input('duet_id'));

        try {
            DB::beginTransaction();

            $model = new Video;
            $model->is_duet = true;
            $model->original_duet_id = $originalDuet->id;
            $model->duet_layout = $request->input('duet_layout') == 'side-by-side' ? 1 : 2;
            $model->profile_id = $pid;
            $model->caption = app(SanitizeService::class)->cleanPlainText($request->description);
            $model->size_kb = intval($videoMeta['size']);
            $model->is_sensitive = $request->filled('is_sensitive') ? (bool) $request->boolean('is_sensitive') : false;
            $model->comment_state = $request->filled('comment_state') ? ($request->input('comment_state') == 4 ? 4 : 0) : 4;
            $model->can_download = $request->filled('can_download') ? $request->boolean('can_download') : false;
            $model->can_duet = $request->filled('can_duet') ? $request->boolean('can_duet') : false;
            $model->can_stitch = $request->filled('can_stitch') ? $request->boolean('can_stitch') : false;
            $model->alt_text = $request->filled('alt_text') ? app(SanitizeService::class)->cleanPlainText($request->alt_text) : null;
            $model->contains_ai = $request->filled('contains_ai') ? $request->boolean('contains_ai') : false;
            $model->contains_ad = $request->filled('contains_ad') ? $request->boolean('contains_ad') : false;
            $model->lang = $request->filled('lang') ? $request->input('lang') : null;
            // @phpstan-ignore-next-line
            $model->media_metadata = $videoMeta;
            $model->save();

            try {
                $s3Path = $request->video->store('videos/'.$pid.'/'.$model->id, 's3');

                if (! $s3Path) {
                    throw new \Exception('Failed to upload video to S3');
                }

                $model->vid = $s3Path;
                $model->save();

            } catch (\Exception $e) {
                if (config('logging.dev_log')) {
                    Log::error('S3 upload failed for video', [
                        'user_id' => $request->user()->id,
                        'video_id' => $model->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                throw new \Exception('Failed to upload video file. Please try again.');
            }

            if ($request->filled('description')) {
                $model->syncHashtagsFromCaption();
                $model->syncMentionsFromCaption();
            }

            $profile->video_count = $profile->videos->count();
            $profile->save();

            DB::commit();

            $config = app(ConfigService::class);

            Bus::batch([
                [
                    new CompositeDuetVideo($model),
                ],
                [
                    new VideoThumbnailJob($model),
                ],
                [
                    new VideoDuetNotification($model),
                ],
            ])->finally(function (Batch $batch) use ($model) {
                $config = app(ConfigService::class);
                if ($config->federation()) {
                    app(FederationDispatcher::class)->dispatchVideoCreation($model);
                }
            })->dispatch();

            return $this->success();

        } catch (\Exception $e) {
            DB::rollBack();

            if ($s3Path) {
                try {
                    Storage::disk('s3')->delete($s3Path);

                    if (config('logging.dev_log')) {
                        Log::info('Cleaned up S3 file after error', ['path' => $s3Path]);
                    }
                } catch (\Exception $deleteError) {
                    Log::error('Failed to delete S3 file during cleanup', [
                        'path' => $s3Path,
                        'error' => $deleteError->getMessage(),
                    ]);
                }
            }

            if ($model && $model->exists) {
                try {
                    $model->delete();
                    if (config('logging.dev_log')) {
                        Log::info('Cleaned up video model after error', ['video_id' => $model->id]);
                    }
                } catch (\Exception $deleteError) {
                    if (config('logging.dev_log')) {
                        Log::error('Failed to delete video model during cleanup', [
                            'video_id' => $model->id,
                            'error' => $deleteError->getMessage(),
                        ]);
                    }
                }
            }

            if (config('logging.dev_log')) {
                Log::error('Video upload failed', [
                    'user_id' => $request->user()->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'An error occurred while uploading your video. Please try again.',
                'error' => 'Upload failed',
            ], 500);
        }
    }
}
