<?php

namespace App\Enums;

enum GarageBillingUnit: string
{
    case Month = 'month';
    case CalendarDay = 'calendar_day';
    case TwentyFourHours = 'twenty_four_hours';

    public function label(): string
    {
        return match ($this) {
            self::Month => __('Mese'),
            self::CalendarDay => __('Giorno di calendario'),
            self::TwentyFourHours => __('Ogni 24 ore iniziate'),
        };
    }
}
