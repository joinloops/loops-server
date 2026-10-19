<?php

namespace App\Exceptions;

use Exception;

class UsernameChangeException extends Exception
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
        public readonly array $meta = [],
    ) {
        parent::__construct($message);
    }
}
