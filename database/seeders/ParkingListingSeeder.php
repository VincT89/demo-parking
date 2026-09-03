<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\Platform;
use App\Models\ParkingListing;
use Illuminate\Database\Seeder;

class ParkingListingSeeder extends Seeder
{
    public function run(): void
    {
        $parkings = Parking::all();
        $platforms = Platform::all();

        foreach ($parkings as $parking) {
            foreach ($platforms as $platform) {
                $externalId = null;
                $isActive = false;

                if ($parking->name === 'Parcheggio Centrale') {
                    if ($platform->slug === 'parking-my-car') {
                        $externalId = '2248';
                        $isActive = true;
                    } elseif ($platform->slug === 'vologio') {
                        $externalId = null; // in attesa di discovery
                        $isActive = false;
                    } elseif ($platform->slug === 'parkos') {
                        $externalId = '1895';
                        $isActive = true;
                    } elseif ($platform->slug === 'website') {
                        $externalId = null;
                        $isActive = true; // attivo ma escluso dal sync
                    }
                }

                ParkingListing::firstOrCreate([
                    'parking_id'     => $parking->id,
                    'platform_id'    => $platform->id,
                ], [
                    'is_active'      => $isActive,
                    'external_id'    => $externalId,
                ]);
            }
        }
    }
}