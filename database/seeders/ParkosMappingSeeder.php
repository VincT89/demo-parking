<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use Illuminate\Database\Seeder;

class ParkosMappingSeeder extends Seeder
{
    public function run(): void
    {
        $parkos = Platform::where('slug', 'parkos')->firstOrFail();

        $autoOpen = ParkingProduct::where('code', 'auto_open')
            ->where('is_active', true)
            ->firstOrFail();

        $parkingId = config('services.parkos.parking_id', 1);
        $locationId = config('services.parkos.location_id', '1895');

        ParkingListing::updateOrCreate(
            [
                'parking_id' => $parkingId,
                'platform_id' => $parkos->id,
            ],
            [
                'external_id' => $locationId,
                'is_active' => true,
            ]
        );

        $mappings = [
            $locationId . ':shuttle:outdoor' => 'Parkos navetta scoperto',
            $locationId . ':shuttle:indoor'  => 'Parkos navetta coperto gestito come scoperto',
            $locationId . ':valet:outdoor'   => 'Parkos valet scoperto',
            $locationId . ':valet:indoor'    => 'Parkos valet coperto gestito come scoperto',
        ];

        foreach ($mappings as $externalRef => $externalName) {
            PlatformProductMapping::updateOrCreate(
                [
                    'platform_id' => $parkos->id,
                    'external_ref' => $externalRef,
                ],
                [
                    'parking_product_id' => $autoOpen->id,
                    'external_name' => $externalName,
                    'is_active' => true,
                ]
            );
        }
    }
}
