<?php

namespace App\Support;

class PriceFormatter
{
    public static function decimal(float $amount): string
    {
        $english = app()->getLocale() === 'en_GB';

        return number_format($amount, 2, $english ? '.' : ',', $english ? ',' : '.');
    }
}
