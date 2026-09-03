<?php

namespace App\Actions;

use App\Integrations\AdapterRegistry;
use App\Integrations\ReservationImportPayloadFactory;
use App\Models\ParkingListing;
use App\Services\ReservationService;
use App\Services\Results\ImportAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncListingAction
{
    public function __construct(
        private AdapterRegistry $registry,
        private ReservationService $reservationService,
        private ReservationImportPayloadFactory $payloadFactory,
        private \App\Services\OverbookingNotificationService $overbookingNotificationService
    ) {}

    /**
     * Executes the synchronization for a single ParkingListing.
     * Returns an array of statistics.
     */
    public function execute(ParkingListing $listing, Carbon $from, Carbon $to, bool $dryRun = false, string $mode = 'modified'): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'failed'  => 0,
            'skipped' => 0,
            'errors'  => [],
            'warnings' => [],
            'skipped_reasons' => [],
        ];

        try {
            // 1. Get the adapter
            $adapter = $this->registry->forPlatform($listing->platform);

            // 2. Fetch reservations
            $normalizedReservations = $adapter->fetchReservations($listing, $from, $to, $mode);

            // 3. Process each reservation
            foreach ($normalizedReservations as $normalized) {
                try {
                    // Intercept cancellation before resolving product
                    $isCancellation =
                        $normalized->status === ReservationStatus::Cancelled->value
                        || $normalized->platform_cancelled_at !== null;

                    if ($isCancellation) {
                        $payload = $this->payloadFactory->makeCancellationPayload($normalized);
                    
                        if ($dryRun) {
                            $exists = Reservation::where('parking_listing_id', $listing->id)
                                ->where('external_id', $payload['external_id'])
                                ->exists();
                    
                            $exists ? $stats['updated']++ : $stats['skipped']++;
                            continue;
                        }
                    
                        $result = $this->reservationService->importFromExternal($listing, $payload);
                    } else {
                        // Resolve internal product for normal imports
                        $product = $adapter->resolveProduct($listing, $normalized->external_product_ref);
                        
                        // Build payload
                        $payload = $this->payloadFactory->makePayload($normalized, $product);
                    
                        if ($dryRun) {
                            $exists = Reservation::where('parking_listing_id', $listing->id)
                                ->where('external_id', $payload['external_id'])
                                ->exists();
                                
                            if ($exists) {
                                $stats['updated']++;
                            } else {
                                $stats['created']++;
                            }
                            continue;
                        }
                    
                        // Import via ReservationService
                        $result = $this->reservationService->importFromExternal($listing, $payload);
                    }

                    if ($result->isSuccess()) {
                        if ($result->action === ImportAction::Created) {
                            $stats['created']++;
                            if (!$dryRun && $result->reservation) {
                                $this->overbookingNotificationService->checkAndNotifyForReservation($result->reservation);
                            }
                        } elseif ($result->action === ImportAction::Updated) {
                            $stats['updated']++;
                            if (!$dryRun && $result->reservation) {
                                $this->overbookingNotificationService->checkAndNotifyForReservation($result->reservation);
                            }
                        } else {
                            $stats['skipped']++;
                            if ($result->action === ImportAction::Skipped && $result->error) {
                                $stats['skipped_reasons'][] = "External ID {$normalized->external_id}: " . $result->error;
                            }
                        }
                    } else {
                        $stats['failed']++;
                        $stats['errors'][] = "External ID {$normalized->external_id}: " . $result->error;
                    }

                } catch (\Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = "External ID {$normalized->external_id}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            // Fatal error fetching or adapter missing
            $stats['failed']++;
            $stats['errors'][] = "Fatal Error: " . $e->getMessage();
        }

        if (count($stats['errors']) > 0) {
            Log::error("SyncListingAction errors for listing {$listing->id}", $stats['errors']);
        }

        return $stats;
    }
}
