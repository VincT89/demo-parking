<?php

namespace App\Enums;

enum ShuttleDirection: string
{
    case Outbound = 'outbound';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => __('Parcheggio → aeroporto'),
            self::Return => __('Aeroporto → parcheggio'),
        };
    }
}
