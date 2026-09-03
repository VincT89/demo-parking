<?php

namespace App\Services;

class AvailabilityResult
{
    public function __construct(
        public readonly bool $available,
        public readonly int $availableSpots,
        public readonly ?string $reason = null,
    ) {}

    public static function available(int $availableSpots): self
    {
        return new self(
            available: true,
            availableSpots: $availableSpots,
        );
    }

    public static function unavailable(string $reason, int $availableSpots = 0): self
    {
        return new self(
            available: false,
            availableSpots: $availableSpots,
            reason: $reason,
        );
    }
}