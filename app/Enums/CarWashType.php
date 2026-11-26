<?php

namespace App\Enums;

enum CarWashType: string
{
    case Interior = 'interior';
    case Exterior = 'exterior';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Interior => __('Lavaggio interno'),
            self::Exterior => __('Lavaggio esterno'),
            self::Both => __('Lavaggio interno + esterno'),
        };
    }

    public function surcharge(): float
    {
        /** Tariffe provvisorie concordate, applicate una sola volta per prenotazione. */
        return $this === self::Both ? 20.0 : 10.0;
    }
}
