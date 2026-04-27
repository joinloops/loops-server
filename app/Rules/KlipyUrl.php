<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KlipyUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $parts = parse_url($value);

        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if ($parts['scheme'] !== 'https') {
            $fail('The :attribute must use https.');

            return;
        }

        $host = rtrim(strtolower($parts['host']), '.');

        if ($host !== 'klipy.com' && ! str_ends_with($host, '.klipy.com')) {
            $fail('The :attribute must be hosted on klipy.com.');
        }
    }
}
