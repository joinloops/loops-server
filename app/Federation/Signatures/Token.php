<?php

namespace App\Federation\Signatures;

final class Token
{
    public function __construct(public readonly string $value) {}
}
