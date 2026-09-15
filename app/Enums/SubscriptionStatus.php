<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Attivo'),
            self::Suspended => __('Sospeso'),
            self::Cancelled => __('Annullato'),
            self::Expired => __('Scaduto'),
        };
    }

    public function reservesCapacity(): bool
    {
        return $this === self::Active;
    }
}
