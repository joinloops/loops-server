<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Models\CommentReply;
use App\Models\Hashtag;
use App\Models\Report;
use App\Models\StarterKit;
use App\Models\Video;
use App\Models\VideoSound;
use App\Services\AccountService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 */
class ReportResource extends JsonResource
{
    public function __construct(Report $resource)
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
        $contentType = null;
        $contentPreview = null;

        if ($this->reported_profile_id) {
            $contentType = 'profile';
            $contentPreview = AccountService::get($this->reported_profile_id);
        } elseif ($this->reported_video_id) {
            $contentType = 'video';
            $videoContent = Video::find($this->reported_video_id);
            if ($videoContent) {
                $contentPreview = (new VideoResource($videoContent));
            }
        } elseif ($this->reported_comment_id) {
            $contentType = 'comment';
            $comment = Comment::find($this->reported_comment_id);
            if ($comment) {
                $contentPreview = new CommentResource($comment);
            }
        } elseif ($this->reported_comment_reply_id) {
            $contentType = 'reply';
            $comment = CommentReply::find($this->reported_comment_reply_id);
            if ($comment) {
                $contentPreview = (new CommentReplyResource($comment))->toArray(request());
                $contentPreview['parent'] = new CommentResource($comment->parent);
            }
        } elseif ($this->reported_hashtag_id) {
            $contentType = 'hashtag';
            $hashtag = Hashtag::find($this->reported_hashtag_id);
            if ($hashtag) {
                $contentPreview = (new AdminHashtagResource($hashtag))->toArray(request());
            }
        } elseif ($this->reported_starter_kit_id) {
            $contentType = 'starter_kit';
            $kit = StarterKit::find($this->reported_starter_kit_id);
            $contentPreview = $kit ? (new AdminStarterKitResource($kit))->toArray(request()) : [];
        } elseif ($this->reported_sound_id) {
            $contentType = 'sound';
            $sound = VideoSound::find($this->reported_sound_id);
            $contentPreview = $sound ? (new VideoSoundResource($sound))->toArray(request()) : [];
        }

        return [
            'id' => $this->id,
            'reporter' => AccountService::get($this->reporter_profile_id),
            'content_type' => $contentType,
            'content_preview' => $contentPreview,
            'admin_notes' => $this->admin_notes,
            'user_message' => $this->user_message,
            'is_remote' => (bool) $this->is_remote,
            'reason' => ReportService::getById($this->report_type),
            'status' => $this->admin_seen == 0 ? 'pending' : 'resolved',
            'created_at' => $this->created_at,
        ];
    }
}
