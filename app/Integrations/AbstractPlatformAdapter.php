<?php

namespace App\Integrations;

use App\Integrations\Contracts\PlatformAdapterInterface;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\PlatformProductMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

abstract class AbstractPlatformAdapter implements PlatformAdapterInterface
{
    /**
     * Resolves the internal ParkingProduct for a given external reference.
     * Enforces strict resolution rules:
     * - 0 active mappings -> Exception (skip/error)
     * - 1 active mapping -> returns Product
     * - >1 active mappings -> Exception (explicit error, configuration issue)
     * 
     * @throws \Exception
     */
    public function resolveProduct(ParkingListing $listing, string $externalRef): ParkingProduct
    {
        $mappings = PlatformProductMapping::query()
            ->where('platform_id', $listing->platform_id)
            ->where('external_ref', $externalRef)
            ->active()
            ->get();

        if ($mappings->isEmpty()) {
            throw new \Exception("Nessun mapping attivo trovato per la piattaforma {$listing->platform->name} con external_ref '{$externalRef}'.");
        }

        if ($mappings->count() > 1) {
            throw new \Exception("Mapping ambiguo: trovati più mapping attivi per external_ref '{$externalRef}' sulla piattaforma {$listing->platform->name}.");
        }

        $mapping = $mappings->first();
        $product = ParkingProduct::whereKey($mapping->parking_product_id)->first();

        if (!$product) {
            throw new \Exception("Il mapping punta a un prodotto interno inesistente (ID: {$mapping->parking_product_id}).");
        }

        if ($product->parking_id !== $listing->parking_id) {
            throw new \Exception("Il prodotto mappato appartiene a un parcheggio diverso rispetto al listing in elaborazione.");
        }

        // Regola commerciale: i prodotti coperti sono inattivi e vengono gestiti come scoperti.
        if ($product->code === 'auto_covered' && !$product->is_active) {
            $fallback = ParkingProduct::where('parking_id', $listing->parking_id)->where('code', 'auto_open')->first();
            if ($fallback) {
                \Illuminate\Support\Facades\Log::warning('Fallback prodotto coperto inattivo gestito come scoperto', [
                    'platform' => $listing->platform->slug,
                    'original_product' => $product->code,
                    'fallback_product' => $fallback->code,
                ]);
                $product = $fallback;
            }
        } elseif ($product->code === 'truck_covered' && !$product->is_active) {
            $fallback = ParkingProduct::where('parking_id', $listing->parking_id)->where('code', 'truck_open')->first();
            if ($fallback) {
                \Illuminate\Support\Facades\Log::warning('Fallback prodotto coperto inattivo gestito come scoperto', [
                    'platform' => $listing->platform->slug,
                    'original_product' => $product->code,
                    'fallback_product' => $fallback->code,
                ]);
                $product = $fallback;
            }
        }

        return $product;
    }

    /**
     * Helper to make HTTP requests with a configured timeout.
     */
    protected function makeRequest(string $method, string $url, array $options = [])
    {
        return Http::timeout(15)->send($method, $url, $options);
    }

    public function defaultSyncWindow(): array
    {
        return [
            Carbon::today()->subDays(30),
            Carbon::today()->addDays(90),
        ];
    }
}
