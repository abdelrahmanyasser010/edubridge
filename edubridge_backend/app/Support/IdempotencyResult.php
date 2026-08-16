<?php

namespace App\Support;

final readonly class IdempotencyResult
{
    public function __construct(
        public mixed $payload,
        public int $status,
        public bool $replayed,
    ) {}
}
