<?php

namespace App\Services;

use App\Models\Parking;
use App\Enums\CarWashType;
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

    /** Restituisce il parcheggio disponibile, il totale e i supplementi applicati ai servizi scelti. */
    public function findFirstAvailable(
        string $productCode,
        Carbon $startsAt,
        Carbon $endsAt,
        int $spots = 1,
        bool $isValet = false,
        ?CarWashType $carWash = null
    ): array {
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
                $valetSurcharge = $isValet ? PricingService::VALET_SURCHARGE : 0.0;
                $carWashSurcharge = $carWash?->surcharge() ?? 0.0;

                return [
                    'parking' => $parking,
                    'product' => $product,
                    'price'   => $totalPrice + $valetSurcharge + $carWashSurcharge,
                    'valet_surcharge' => $valetSurcharge,
                    'car_wash_surcharge' => $carWashSurcharge,
                ];
            }
        }

        throw new Exception(__('Nessun parcheggio disponibile per le date e il prodotto richiesti.'));
    }
}
