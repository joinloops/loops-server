<?php

namespace App\Services\Migration;

use RuntimeException;

class MigrationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid', public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
