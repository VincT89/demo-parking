<?php

namespace App\Services;

use App\Models\Reservation;

class ReservationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?Reservation $reservation = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(Reservation $reservation): self
    {
        return new self(
            success: true,
            reservation: $reservation,
        );
    }

    public static function failed(string $error): self
    {
        return new self(
            success: false,
            error: $error,
        );
    }
}