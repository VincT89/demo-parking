<?php

namespace App\Services;

use App\Enums\GarageBillingUnit;
use App\Models\GarageRate;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class GaragePricingService
{
    public function quote(
        GarageRate $rate,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): string {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('The end of the stay must be after its start.');
        }

        $units = match ($rate->billing_unit) {
            GarageBillingUnit::Month => 1,
            GarageBillingUnit::CalendarDay => max(
                (int) $rate->minimum_units,
                (int) $startsAt->copy()->startOfDay()
                    ->diffInDays($endsAt->copy()->startOfDay()) + 1,
            ),
            GarageBillingUnit::TwentyFourHours => max(
                (int) $rate->minimum_units,
                (int) ceil(max(
                    0,
                    $startsAt->diffInMinutes($endsAt) - (int) $rate->grace_minutes,
                ) / 1440),
            ),
        };

        return number_format(round((float) $rate->price * $units, 2), 2, '.', '');
    }
}
