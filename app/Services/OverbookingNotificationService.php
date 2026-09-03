<?php

namespace App\Services;

use App\Models\Platform;
use App\Models\Reservation;
use App\Models\Parking;
use App\Mail\ParkingCapacityReachedMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OverbookingNotificationService
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {}

    public function checkAndNotifyForReservation(Reservation $reservation): void
    {
        $reservation->loadMissing('parking');

        $parking = $reservation->parking;

        if (!$parking) {
            return;
        }

        $capacity = $parking->getComputedTotalSpots();

        if ($capacity <= 0) {
            return;
        }

        foreach ($this->daysCoveredByReservation($reservation) as $day) {
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->addDay()->startOfDay();

            $occupied = Reservation::where('parking_id', $parking->id)
                ->active()
                ->overlapping($start, $end)
                ->sum('spots');

            if ($occupied < $capacity) {
                continue;
            }

            $this->notifyPlatforms($parking, $day, (int) $occupied, (int) $capacity);
        }
    }

    private function daysCoveredByReservation(Reservation $reservation): array
    {
        $start = $reservation->starts_at->copy()->startOfDay();
        $end = $reservation->ends_at->copy()->startOfDay();

        $days = [];

        while ($start->lte($end)) {
            $days[] = $start->copy();
            $start->addDay();
        }

        return $days;
    }

    private function notifyPlatforms(Parking $parking, Carbon $day, int $occupied, int $capacity): void
    {
        $platforms = Platform::active()
            ->whereHas('listings', function ($query) use ($parking) {
                $query->where('parking_id', $parking->id)
                    ->where('is_active', true);
            })
            ->whereNotNull('contact_email')
            ->where('contact_email', '!=', '')
            ->get();

        foreach ($platforms as $platform) {
            $cacheKey = "overbooking_alert_{$parking->id}_{$platform->id}_{$day->format('Y-m-d')}";

            $expiresAt = $day->copy()->addDay()->endOfDay();
            if ($expiresAt->lessThan(now()->addDays(7))) {
                $expiresAt = now()->addDays(7);
            }

            if (! Cache::add($cacheKey, true, $expiresAt)) {
                continue;
            }

            Mail::to($platform->contact_email)->queue(
                new ParkingCapacityReachedMail(
                    parking: $parking,
                    platform: $platform,
                    day: $day,
                    occupied: $occupied,
                    capacity: $capacity
                )
            );
        }
    }
}
