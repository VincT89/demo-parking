<?php

namespace App\Services;

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\Reservation;
use App\Models\AvailabilityBlock;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Controlla la disponibilità su tutto il parcheggio.
     */
    public function checkParking(
        Parking $parking,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spotsRequested = 1,
        ?ParkingListing $listing = null
    ): AvailabilityResult {
        throw new \LogicException('Global parking availability is deprecated. Use product-based checks only.');
    }

    /**
     * Controlla disponibilità su tutto il parcheggio escludendo un record (update).
     */
    public function checkParkingExcluding(
        Parking $parking,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spotsRequested = 1,
        int $excludeReservationId = 0,
        ?ParkingListing $listing = null
    ): AvailabilityResult {
        throw new \LogicException('Global parking availability is deprecated. Use product-based checks only.');
    }

    public function checkProductCapacity(
        \App\Models\ParkingProduct $product,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spotsRequested = 1
    ): AvailabilityResult {
        return $this->checkProductCapacityExcluding($product, $startsAt, $endsAt, $spotsRequested, 0);
    }

    public function checkProductCapacityExcluding(
        \App\Models\ParkingProduct $product,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spotsRequested = 1,
        int $excludeReservationId = 0
    ): AvailabilityResult {
        if (! $product->is_active) {
            return AvailabilityResult::unavailable('Prodotto non attivo');
        }

        $parking = $product->parking;

        // Capacità specifica del prodotto
        $reservedSpotsProd = $this->countReservedSpots($product->id, $startsAt, $endsAt, $excludeReservationId);
        $allocatedSpotsProd = $this->countAllocatedSpotsForProduct($product->id, $startsAt, $endsAt);
        $productCapacityAvail = max(0, $product->capacity - $reservedSpotsProd - $allocatedSpotsProd);

        if ($parking->capacity_mode === 'per_product') {
            $availableSpots = $productCapacityAvail;
        } else {
            // Capacità globale del parcheggio (shared pool)
            $reservedSpotsGlobal = $this->countAllReservedSpotsInParking($parking->id, $startsAt, $endsAt, $excludeReservationId);
            $blockedSpotsGlobal = $this->countBlockedSpots($parking->id, $startsAt, $endsAt);
            $allocatedSpotsGlobal = $this->countGlobalAllocatedSpots($parking->id, $startsAt, $endsAt);
            
            // Assumiamo che se total_spots non è definito (0), la capacità globale non sia un limite.
            $parkingTotalSpots = $parking->total_spots > 0 ? $parking->total_spots : 999999;
            $parkingGlobalAvail = max(0, $parkingTotalSpots - $reservedSpotsGlobal - $blockedSpotsGlobal - $allocatedSpotsGlobal);

            // La disponibilità effettiva è il minimo tra quella del prodotto e quella globale del parcheggio
            $availableSpots = max(0, min($productCapacityAvail, $parkingGlobalAvail));
        }

        if ($availableSpots < $spotsRequested) {
            return AvailabilityResult::unavailable(
                "Posti insufficienti per la categoria {$product->name}: {$availableSpots} disponibili, {$spotsRequested} richiesti",
                $availableSpots
            );
        }

        return AvailabilityResult::available($availableSpots);
    }

    public function countReservedSpots(int $productId, Carbon $startsAt, Carbon $endsAt, int $excludeId = 0): int
    {
        $query = Reservation::query()
            ->where('parking_product_id', $productId)
            ->active()
            ->overlapping($startsAt, $endsAt);

        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return (int) $query->lockForUpdate()->sum('spots');
    }

    public function countAllReservedSpotsInParking(int $parkingId, Carbon $startsAt, Carbon $endsAt, int $excludeId = 0): int
    {
        $query = Reservation::query()
            ->where('parking_id', $parkingId)
            ->active()
            ->overlapping($startsAt, $endsAt);

        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return (int) $query->lockForUpdate()->sum('spots');
    }

    /**
     * Calcola i posti bloccati manualmente per un intero parcheggio in un dato periodo.
     */
    public function countBlockedSpots(int $parkingId, Carbon $startsAt, Carbon $endsAt): int
    {
        return (int) AvailabilityBlock::query()
            ->active()
            ->where('parking_id', $parkingId)
            ->overlapping($startsAt, $endsAt)
            ->lockForUpdate()
            ->sum('spots');
    }

    /**
     * Calcola i posti riservati (allocati) per tutto il parcheggio.
     */
    public function countGlobalAllocatedSpots(int $parkingId, Carbon $startsAt, Carbon $endsAt): int
    {
        return (int) \App\Models\ParkingCapacityAllocation::query()
            ->active()
            ->where('parking_id', $parkingId)
            ->whereNull('parking_product_id')
            ->overlapping($startsAt, $endsAt)
            ->lockForUpdate()
            ->sum('spots');
    }

    /**
     * Calcola i posti riservati (allocati) per un prodotto specifico.
     */
    public function countAllocatedSpotsForProduct(int $productId, Carbon $startsAt, Carbon $endsAt): int
    {
        return (int) \App\Models\ParkingCapacityAllocation::query()
            ->active()
            ->where('parking_product_id', $productId)
            ->overlapping($startsAt, $endsAt)
            ->lockForUpdate()
            ->sum('spots');
    }

    public function checkAll(
        int $parkingId,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spotsRequested = 1
    ): array {
        // Obsoleto/da ridisegnare, utile semmai per reportistica
        return [];
    }
}
