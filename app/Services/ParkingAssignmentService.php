<?php

namespace App\Services;

use App\Models\Parking;
use Carbon\Carbon;
use Exception;

class ParkingAssignmentService
{
    protected AvailabilityService $availabilityService;
    protected PricingService $pricingService;

    public function __construct(AvailabilityService $availabilityService, PricingService $pricingService)
    {
        $this->availabilityService = $availabilityService;
        $this->pricingService = $pricingService;
    }

    /**
     * Trova il primo parcheggio disponibile per i parametri richiesti.
     * Restituisce un array con ['parking' => Parking, 'product' => ParkingProduct, 'price' => float]
     * o lancia un'eccezione se nessun parcheggio è disponibile.
     */
    public function findFirstAvailable(string $productCode, Carbon $startsAt, Carbon $endsAt, int $spots = 1): array
    {
        // Recupera i parcheggi attivi ordinati (deterministico per id)
        $parkings = Parking::active()->orderBy('id', 'asc')->get();

        foreach ($parkings as $parking) {
            // Cerca il prodotto logico corrispondente in questo parcheggio
            $product = $parking->products()->where('is_active', true)->where('code', $productCode)->first();

            if (!$product) {
                continue; // Questo parcheggio non offre questo prodotto o è inattivo
            }

            // Verifica la disponibilità effettiva tramite AvailabilityService
            $availability = $this->availabilityService->checkProductCapacityExcluding(
                $product,
                $startsAt,
                $endsAt,
                $spots
            );

            if ($availability->available) {
                // Calcola il prezzo base usando PricingService
                $totalPrice = $this->pricingService->totalForProduct(
                    $product,
                    $startsAt,
                    $endsAt,
                    $spots
                );

                return [
                    'parking' => $parking,
                    'product' => $product,
                    'price'   => $totalPrice,
                ];
            }
        }

        throw new Exception('Nessun parcheggio disponibile per le date e il prodotto richiesti.');
    }
}
