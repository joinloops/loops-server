<?php

namespace App\Http\Resources;

use App\Models\AccessRule;
use App\Services\AccountService;
use App\Support\IpRange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAccessRuleResource extends JsonResource
{
    public function __construct(AccessRule $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rule = $this->rule();
        $account = $rule->created_by ? AccountService::getByUserId($rule->created_by, true, true) : [];
        $details = [];

        if ($rule->type === AccessRule::TYPE_IP && $rule->ip_start && $rule->ip_end) {
            $details = [
                'version' => IpRange::isV4Hex($rule->ip_start) ? 4 : 6,
                'first' => IpRange::fromHex($rule->ip_start),
                'last' => IpRange::fromHex($rule->ip_end),
                'size' => IpRange::sizeLabel($rule->ip_start, $rule->ip_end),
            ];
        }

        return [
            'id' => (string) $rule->id,
            'type' => $rule->type,
            'match_type' => $rule->match_type,
            'value' => $rule->value,
            'details' => $details,
            'blocks_login' => (bool) $rule->blocks_login,
            'blocks_registration' => (bool) $rule->blocks_registration,
            'note' => $rule->note,
            'expires_at' => $rule->expires_at?->toIso8601String(),
            'is_expired' => $rule->isExpired(),
            'hits' => (int) $rule->hits,
            'last_hit_at' => $rule->last_hit_at?->toIso8601String(),
            'source_profile_id' => $rule->source_profile_id ? (string) $rule->source_profile_id : null,
            'created_by' => $rule->created_by ? (string) $rule->created_by : null,
            'created_by_account' => $account,
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }

    protected function rule(): AccessRule
    {
        return $this->resource;
    }
}
