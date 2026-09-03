<?php

namespace App\Integrations\Contracts;

use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use Carbon\Carbon;

interface PlatformAdapterInterface
{
    /**
     * Get the human-readable name of the platform adapter.
     */
    public function getName(): string;

    /**
     * Get the platform slug this adapter handles.
     */
    public function getPlatformSlug(): string;

    /**
     * Fetch reservations from the external platform for a specific listing within a date range.
     * 
     * @return \App\Integrations\DTO\NormalizedReservation[]
     */
    public function fetchReservations(ParkingListing $listing, Carbon $from, Carbon $to, string $mode = 'modified'): array;

    /**
     * Resolve the internal ParkingProduct for a given external product reference.
     */
    public function resolveProduct(ParkingListing $listing, string $externalRef): ParkingProduct;

    /**
     * Get the default synchronization window [from, to] for this adapter.
     * 
     * @return array{0: Carbon, 1: Carbon}
     */
    public function defaultSyncWindow(): array;
}
