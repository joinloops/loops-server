<?php

namespace App\Services\Migration;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class HashingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private $stream;

    private $context;

    private int $bytes = 0;

    public function __construct(StreamInterface $stream, private int $maxBytes)
    {
        $this->stream = $stream;
        $this->context = hash_init('sha256');
    }

    public function read($length): string
    {
        $data = $this->stream->read($length);

        if ($data !== '') {
            $this->bytes += strlen($data);

            if ($this->bytes > $this->maxBytes) {
                throw new MigrationException('Media exceeds the allowed size', 'over_limits');
            }

            hash_update($this->context, $data);
        }

        return $data;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Hashing streams are not seekable');
    }

    public function rewind(): void
    {
        throw new \RuntimeException('Hashing streams are not seekable');
    }

    public function digest(): string
    {
        return hash_final(hash_copy($this->context));
    }

    public function bytes(): int
    {
        return $this->bytes;
    }
}
