<?php

namespace App\Rules;

use App\Services\AccessRuleService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotBlockedIp implements ValidationRule
{
    public function __construct(protected string $context = AccessRuleService::CONTEXT_REGISTER) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = app(AccessRuleService::class);
        $rule = $service->findIpRule(request()->ip(), $this->context);

        if ($rule) {
            $service->recordHit($rule['id'], $this->context);
            $fail($service->message('ip', $this->context));
        }
    }
}
