<?php

namespace App\Enums;

enum GaragePaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('In attesa'),
            self::Paid => __('Pagato'),
            self::Failed => __('Non riuscito'),
            self::Refunded => __('Rimborsato'),
            self::Reversed => __('Stornato'),
        };
    }
}
