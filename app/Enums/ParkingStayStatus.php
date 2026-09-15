<?php

namespace App\Enums;

enum ParkingStayStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('In sosta'),
            self::Completed => __('Uscito'),
            self::Cancelled => __('Annullato'),
        };
    }
}
