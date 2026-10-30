<?php

namespace App\Http\Resources;

use App\Models\Video;
use App\Services\AccountService;
use App\Services\CollabService;
use App\Services\HashidService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @mixin Video
 */
class StudioVideoResource extends JsonResource
{
    public function __construct(Video $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = VideoService::getMediaData($this->id);

        $thumb = data_get($media, 'media.thumbnail', url('/videos/video-placeholder.jpg'));
        $mediaUrl = data_get($media, 'media.src_url', null);

        $status = match ($this->status) {
            1 => 'processing',
            2 => 'published',
            4 => 'archived',
            default => 'Pending'
        };

        $res = [
            'id' => (string) $this->id,
            'account' => AccountService::compact($this->profile_id),
            'caption' => $this->caption,
            'media' => [
                'thumbnail' => $thumb,
                'src_url' => $mediaUrl,
                'hls_url' => null,
            ],
            'status' => $status,
            'is_owner' => true,
            'likes' => $this->likes,
            'shares' => $this->shares,
            'comments' => $this->comments,
            'is_sensitive' => (bool) $this->is_sensitive,
            'created_at' => $this->created_at->format('c'),
        ];

        if ($this->scheduled_at && $this->status == 1) {

            $thumb = url('/storage/videos/video-placeholder.jpg');
            if ($this->has_thumb) {
                if ($this->thumbnail) {
                    $thumb = $this->thumbnail;
                } elseif ($this->thumbnail_path) {
                    $thumb = Storage::disk('s3')->url($this->thumbnail_path);
                } else {
                    $ext = pathinfo($this->vid, PATHINFO_EXTENSION);
                    $url = str_replace('.'.$ext, '.jpg', $this->vid);
                    $thumb = Storage::disk('s3')->url($url);
                }
            }
            $res['media']['thumbnail'] = $thumb;
            $res['status'] = 'scheduled';
            $res['scheduled_at'] = $this->scheduled_at;
        }

        $collab = app(CollabService::class)->studioSummary($this->resource);

        if ($collab) {
            $res['collab'] = $collab;

            if ($this->status !== 2 && $this->has_processed && in_array($collab['state'], ['pending', 'declined', 'expired'], true)) {
                $res['status'] = match ($collab['state']) {
                    'pending' => 'awaiting_collab',
                    default => 'collab_'.$collab['state'],
                };
                $res['media']['thumbnail'] = app(CollabService::class)->videoPreview($this->resource)['thumbnail'];
            }
        }

        if ($this->status === 2) {
            $res = array_merge([
                'hid' => HashidService::encode($this->id),
                'url' => $this->shareUrl(),
                'pinned' => $this->is_pinned,
                'privacy' => 'everyone',
                'views_count' => $this->views,
                'has_liked' => null,
                'permissions' => [
                    'can_comment' => (bool) $this->comment_state == 4,
                    'can_download' => (bool) $this->can_download,
                    'can_duet' => (bool) $this->can_duet,
                    'can_stitch' => (bool) $this->can_stitch,
                ],
                'audio' => [
                    'has_audio' => (bool) $this->has_audio,
                    'id' => (string) 'at:'.$this->id,
                    'count' => 0,
                    'key' => (string) Str::uuid(),
                ],
            ], $res);
        }

        return $res;
    }
}
