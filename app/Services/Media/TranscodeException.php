<?php

namespace App\Services\Media;

class TranscodeException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $stderr = '',
        public readonly bool $permanent = false,
        ?\Throwable $previous = null,
        public readonly ?int $exitCode = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function tail(string $stderr, int $bytes = 3000): string
    {
        $stderr = trim($stderr);

        return strlen($stderr) > $bytes ? '…'.substr($stderr, -$bytes) : $stderr;
    }

    public function stderrTail(int $bytes = 3000): string
    {
        return self::tail($this->stderr, $bytes);
    }

    public function isToolFailure(): bool
    {
        if ($this->exitCode === null) {
            return false;
        }

        return $this->exitCode === 126 || $this->exitCode === 127 || $this->exitCode > 128;
    }
}
