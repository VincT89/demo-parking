<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Platform;
use App\Models\ParkingProduct;
use App\Models\PlatformProductMapping;

class PlatformProductMappingSeeder extends Seeder
{
    public function run(): void
    {
        $parkingMyCar = Platform::where('slug', 'parking-my-car')->first();
        $autoOpen = ParkingProduct::where('code', 'auto_open')->first();

        if ($parkingMyCar && $autoOpen) {
            foreach ([
                '2306' => 'navetta scoperto lascia le chiavi',
                '3869' => 'navetta scoperto tieni le chiavi',
                '2307' => 'Parking My Car model 2307',
            ] as $externalRef => $externalName) {
                PlatformProductMapping::updateOrCreate(
                    [
                        'platform_id' => $parkingMyCar->id,
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
}
