<?php

namespace App\Federation\Signatures;

final class ByteSequence
{
    public function __construct(public readonly string $bytes) {}
}
