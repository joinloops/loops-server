<?php

namespace App\Http\Resources;

use App\Models\VideoCollaboration;
use App\Services\AccountService;
use App\Services\CollabService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A collaboration as seen by the invited coauthor.
 *
 * @mixin VideoCollaboration
 */
class CollabRequestResource extends JsonResource
{
    public function __construct(VideoCollaboration $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $video = $this->video;

        return [
            'id' => (string) $this->id,
            'state' => $this->stateName(),
            'auto_approved' => (bool) $this->auto_approved,
            'inviter' => AccountService::compact($this->inviter_profile_id) ?: AccountService::deletedAccount(),
            'video' => $video ? app(CollabService::class)->videoPreview($video) : null,
            'expires_at' => $this->expires_at?->format('c'),
            'responded_at' => $this->responded_at?->format('c'),
            'created_at' => $this->created_at?->format('c'),
        ];
    }
}
