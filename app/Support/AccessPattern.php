<?php

namespace App\Support;

class AccessPattern
{
    public const MAX_LENGTH = 255;

    protected const BACKTRACK_LIMIT = '20000';

    protected const PROBES = [
        'a',
        'z9',
        'loops',
        'hello.world_42',
        'test@example.com',
        'someone@mail.example.org',
    ];

    public static function compile(string $pattern): string
    {
        $out = '';
        $escaped = false;
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($escaped) {
                $out .= $char;
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $out .= $char;
                $escaped = true;

                continue;
            }

            $out .= $char === '/' ? '\\/' : $char;
        }

        return '/'.$out.'/iu';
    }

    public static function validate(string $pattern): ?string
    {
        if (trim($pattern) === '') {
            return 'Enter a pattern.';
        }

        if (mb_strlen($pattern) > self::MAX_LENGTH) {
            return 'Patterns can be at most '.self::MAX_LENGTH.' characters.';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $pattern)) {
            return 'Patterns cannot contain control characters.';
        }

        if (preg_match('/\\\\(?:[1-9]|k|g)/', $pattern)) {
            return 'Backreferences are not supported in patterns.';
        }

        if (preg_match('/\(\?(?![:=!]|<[=!])/', $pattern)) {
            return 'Only (?: ), lookahead and lookbehind groups are supported.';
        }

        if (self::hasNestedRepeat($pattern)) {
            return 'Nested repeats like (a+)+ are not allowed, they can stall matching.';
        }

        $regex = self::compile($pattern);

        set_error_handler(static fn () => true);

        try {
            $compiles = preg_match($regex, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (! $compiles) {
            return 'This is not a valid pattern.';
        }

        $hits = 0;

        foreach (self::PROBES as $probe) {
            if (self::test($regex, $probe)) {
                $hits++;
            }
        }

        if ($hits === count(self::PROBES)) {
            return 'This pattern matches almost everything, make it more specific.';
        }

        return null;
    }

    public static function hasNestedRepeat(string $pattern): bool
    {
        return (bool) preg_match('/\((?:[^()\\\\]|\\\\.)*(?<!\\\\)[+*}](?:[^()\\\\]|\\\\.)*\)\s*[+*{]/', $pattern);
    }

    public static function test(string $regex, string $subject): bool
    {
        $previous = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', self::BACKTRACK_LIMIT);

        try {
            $result = @preg_match($regex, $subject);
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previous);
        }

        return $result === 1;
    }
}
