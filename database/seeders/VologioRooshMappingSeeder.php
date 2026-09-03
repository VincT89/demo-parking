<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VologioRooshMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ParkingListing::updateOrCreate(
            [
                'parking_id' => 1,
                'platform_id' => 3,
            ],
            [
                'external_id' => env('VOLOGIO_SERVICE_LOCATION_ID'),
                'is_active' => true,
            ]
        );

        \App\Models\PlatformProductMapping::updateOrCreate(
            [
                'platform_id' => 3,
                'parking_product_id' => 1,
            ],
            [
                'external_ref' => env('VOLOGIO_SERVICE_ID'),
                'external_name' => 'Roosh Shuttle scoperto',
                'is_active' => true,
            ]
        );
    }
}
