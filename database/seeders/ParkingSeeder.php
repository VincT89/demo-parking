<?php

namespace Database\Seeders;

use App\Models\Parking;
use Illuminate\Database\Seeder;

class ParkingSeeder extends Seeder
{
    public function run(): void
    {
        Parking::firstOrCreate(
            ['name' => 'Parcheggio Centrale'],
            [
                'address'     => 'Via Aeroporto 1',
                'total_spots' => 2000,
                'notes'       => 'Parcheggio principale',
                'is_active'   => true,
            ]
        );

        // Parking::firstOrCreate(
        //     ['name' => 'Parcheggio Sud (Inattivo)'],
        //     [
        //         'address'     => 'Via Provinciale 12',
        //         'total_spots' => 500,
        //         'notes'       => 'Parcheggio secondario attualmente chiuso',
        //         'is_active'   => false,
        //     ]
        // );

        // Parking::firstOrCreate(
        //     ['name' => 'Parcheggio VIP (Per Product)'],
        //     [
        //         'address'       => 'Via Privata 99',
        //         'total_spots'   => 150,
        //         'capacity_mode' => 'per_product',
        //         'notes'         => 'Aree fisicamente separate per ogni prodotto',
        //         'is_active'     => true,
        //     ]
        // );
    }
}