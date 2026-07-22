<?php

namespace App\Services;

class DmPermissionResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly int $status = 403,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(string $reason, int $status = 403): self
    {
        return new self(false, $reason, $status);
    }
}
