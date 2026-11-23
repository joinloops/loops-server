<?php

namespace App\Rules;

use App\Services\AccessRuleService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotBlockedUsername implements ValidationRule
{
    public function __construct(protected bool $record = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $service = app(AccessRuleService::class);

        if ($service->isUsernameBlocked($value, $this->record)) {
            $fail($service->message('username', AccessRuleService::CONTEXT_REGISTER));
        }
    }
}
