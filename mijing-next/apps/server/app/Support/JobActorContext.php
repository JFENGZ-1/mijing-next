<?php

namespace App\Support;

final readonly class JobActorContext
{
    public function __construct(
        public int $staffId,
        public ?string $requestId = null,
    ) {}

    public static function fromRequest(int $staffId, ?string $requestId): self
    {
        return new self($staffId, $requestId);
    }
}
