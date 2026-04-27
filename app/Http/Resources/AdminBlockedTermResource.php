<?php

namespace App\Http\Resources;

use App\Models\BlockedTerm;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlockedTerm
 */
class AdminBlockedTermResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->created_by ? AccountService::getByUserId($this->created_by, true, true) : [];

        return [
            'id' => (string) $this->id,
            'term' => $this->term,
            'type' => $this->type,
            'note' => $this->note,
            'created_by_account' => $account,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
