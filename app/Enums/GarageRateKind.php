<?php

namespace App\Enums;

enum GarageRateKind: string
{
    case Subscription = 'subscription';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => __('Abbonamento mensile'),
            self::WalkIn => __('Sosta giornaliera'),
        };
    }
}
