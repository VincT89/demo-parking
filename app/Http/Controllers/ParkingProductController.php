<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Http\Requests\UpsertParkingProductsRequest;
use Illuminate\Support\Facades\DB;

class ParkingProductController extends Controller
{
    /**
     * Upsert all products for the specified parking.
     */
    public function upsertForParking(UpsertParkingProductsRequest $request, Parking $parking)
    {
        DB::transaction(function () use ($request, $parking) {
            $payloadProducts = $request->input('products', []);

            foreach ($payloadProducts as $prodData) {
                $isDelete = !empty($prodData['delete']) && $prodData['delete'] == true;
                $id = $prodData['id'] ?? null;

                if ($isDelete) {
                    if ($id) {
                        ParkingProduct::whereKey($id)->update(['is_active' => false]);
                    }
                    continue;
                }

                $mappedData = [
                    'parking_id' => $parking->id,
                    'name' => $prodData['name'],
                    'code' => $prodData['code'],
                    'capacity' => $prodData['capacity'],
                    'price' => $prodData['price'],
                    'is_active' => $prodData['is_active'] ?? false,
                    'sort_order' => $prodData['sort_order'] ?? 0,
                ];

                if ($id) {
                    ParkingProduct::whereKey($id)->update($mappedData);
                } else {
                    ParkingProduct::create($mappedData);
                }
            }
        });

        // Trigger Cache clear nel caso in cui i prodotti siano mutati e l'AlertCount debba refresharsi
        if (auth()->check()) {
            cache()->forget('alert_count_' . auth()->id());
        }

        return redirect()->route('parkings.edit', $parking)->with('success', 'Prodotti del parcheggio aggiornati correttamente.');
    }
}
