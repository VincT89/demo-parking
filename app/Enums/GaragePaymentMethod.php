<?php

namespace App\Enums;

enum GaragePaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Stripe = 'stripe';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Contanti'),
            self::Card => __('Carta / POS'),
            self::BankTransfer => __('Bonifico'),
            self::Stripe => __('Stripe'),
            self::Other => __('Altro'),
        };
    }
}
