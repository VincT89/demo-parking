<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ParkingProduct;
use Carbon\Carbon;

class PricingService
{
    public function amountForReservation(Reservation $reservation): int
    {
        // returns cents
        return (int) round($reservation->price * 100);
    }

    public function decimalAmountForReservation(Reservation $reservation): string
    {
        return number_format($reservation->price, 2, '.', '');
    }

    public function billableCalendarDays(Carbon $startsAt, Carbon $endsAt): int
    {
        return max(
            1,
            $startsAt->copy()->startOfDay()
                ->diffInDays($endsAt->copy()->startOfDay()) + 1
        );
    }

    public function totalForProduct(
        ParkingProduct $product,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spots = 1
    ): float {
        return $product->price
            * $this->billableCalendarDays($startsAt, $endsAt)
            * $spots;
    }
}
