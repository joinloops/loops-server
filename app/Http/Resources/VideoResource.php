<?php

namespace App\Http\Resources;

use App\Models\Hashtag;
use App\Models\Video;
use App\Services\AccountService;
use App\Services\CollabService;
use App\Services\LikeService;
use App\Services\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Video
 */
class VideoResource extends JsonResource
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

        $pid = $this->getAuthenticatedProfileId($request);
        $hasLiked = $pid ? app(LikeService::class)->hasLikedVideo((string) $this->id, (string) $pid) : false;
        $hasBookmarked = $this->is_bookmarked ?? false;

        $res = [
            'id' => (string) $this->id,
            'account' => AccountService::compact($this->profile_id),
            'coauthors' => app(CollabService::class)->coauthorAccounts($this->id),
            'caption' => $this->caption,
            'url' => $this->shareUrl(),
            'shortcode' => $this->hashid(),
            'is_owner' => $pid && (int) $this->profile_id === (int) $pid,
            'is_sensitive' => (bool) $this->is_sensitive,
            'is_local' => $this->is_local,
            'media' => [
                'thumbnail' => $thumb,
                'src_url' => $mediaUrl,
                'hls_url' => null,
                'alt_text' => $this->alt_text,
                'duration' => $this->duration,
                'width' => $this->width,
                'height' => $this->height,
            ],
            'pinned' => $this->is_pinned,
            'likes' => $this->likes,
            'shares' => $this->shares,
            'comments' => $this->comment_state === 4 ? $this->comments : 0,
            'bookmarks' => $this->bookmarks,
            'has_liked' => $hasLiked,
            'has_bookmarked' => (bool) $hasBookmarked,
            'is_edited' => $this->is_edited,
            'lang' => $this->lang,
            'tags' => $this->hashtags->map(fn (Hashtag $tag) => $tag->name),
            'mentions' => $this->mentions,
            'permissions' => [
                'can_comment' => (bool) $this->comment_state == 4,
                'can_download' => (bool) $this->can_download,
                'can_duet' => (bool) $this->can_duet,
                'can_stitch' => (bool) $this->can_stitch,
                'can_embed' => (bool) $this->can_embed && ! $this->is_sensitive,
            ],
            'audio' => [
                'has_audio' => (bool) $this->has_audio,
                'id' => (string) 'at:'.$this->id,
                'count' => 0,
                'key' => (string) Str::uuid(),
                'sound_id' => $this->sound_id ? (string) $this->sound_id : null,
            ],
            'meta' => [
                'contains_ai' => $this->contains_ai,
                'contains_ad' => $this->contains_ad,
            ],
            'created_at' => $this->created_at->format('c'),
        ];

        return $res;
    }

    /**
     * Get the authenticated user's profile ID
     */
    private function getAuthenticatedProfileId(Request $request): ?int
    {
        $pid = false;
        $user = auth('web')->user() ?? auth('api')->user();
        if ($user) {
            $pid = $user->profile_id;
        }

        return $pid;
    }
}
