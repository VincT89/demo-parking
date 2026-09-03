<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\ParkingProduct;
use Illuminate\Database\Seeder;

class ParkingProductsSeeder extends Seeder
{
    public function run(): void
    {
        $parkings = Parking::where('is_active', true)->get();

        if ($parkings->isEmpty()) {
            $this->command?->warn('Nessun parcheggio attivo trovato. Seeder parking_products saltato.');
            return;
        }

        $products = [
            [
                'code' => 'auto_open',
                'name' => 'Auto/Moto scoperto',
                'capacity' => 1000,
                'price' => 5.00,
                'sort_order' => 10,
            ],
            [
                'code' => 'auto_covered',
                'name' => 'Auto/Moto coperto',
                'capacity' => 600,
                'price' => 7.80,
                'sort_order' => 20,
                'is_active' => false,
            ],
            [
                'code' => 'truck_open',
                'name' => 'Camion scoperto',
                'capacity' => 200,
                'price' => 14.80,
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'truck_covered',
                'name' => 'Camion coperto',
                'capacity' => 200,
                'price' => 17.80,
                'sort_order' => 40,
                'is_active' => false,
            ],
        ];

        foreach ($parkings as $parking) {
            foreach ($products as $product) {
                // Adattiamo la capacità al parcheggio Nord (che ha metà capienza totale) per realismo
                $capacity = $parking->name === 'Parcheggio Nord' ? $product['capacity'] / 2 : $product['capacity'];

                ParkingProduct::updateOrCreate(
                    [
                        'parking_id' => $parking->id,
                        'code' => $product['code'],
                    ],
                    [
                        'name' => $product['name'],
                        'capacity' => $capacity,
                        'price' => $product['price'],
                        'is_active' => $product['is_active'] ?? true,
                        'sort_order' => $product['sort_order'],
                    ]
                );
            }
        }
    }
}
