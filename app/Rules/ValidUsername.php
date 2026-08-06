<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidUsername implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid string.');
            return;
        }

        if (! preg_match('/^[a-zA-Z0-9_.-]+$/', $value)) {
            $fail('The :attribute can only contain letters, numbers, underscores, hyphens, and periods.');
            return;
        }

        if (str_starts_with($value, '.')) {
            $fail('The :attribute cannot start with a period.');
            return;
        }

        if (str_starts_with($value, '-')) {
            $fail('The :attribute cannot start with a hyphen.');
            return;
        }

        if (str_ends_with($value, '.')) {
            $fail('The :attribute cannot end with a period.');
            return;
        }

        if (str_ends_with($value, '-')) {
            $fail('The :attribute cannot end with a hyphen.');
            return;
        }

        if (str_contains($value, '--')) {
            $fail('The :attribute cannot contain consecutive hyphens.');
            return;
        }

        if (str_contains($value, '..')) {
            $fail('The :attribute cannot contain consecutive periods.');
            return;
        }
    }
}
