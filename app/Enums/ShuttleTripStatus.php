<?php

namespace App\Enums;

enum ShuttleTripStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Departed = 'departed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => __('Proposto'),
            self::Confirmed => __('Confermato'),
            self::Departed => __('Partito'),
            self::Completed => __('Completato'),
            self::Cancelled => __('Annullato'),
        };
    }
}
