<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'name'          => 'Parking My Car',
                'slug'          => 'parking-my-car',
                'website'       => 'https://www.parkingmycar.it',
                'contact_email' => 'partner@parkingmycar.it',
                'is_active'     => true,
            ],
            [
                'name'          => 'Parkos',
                'slug'          => 'parkos',
                'website'       => 'https://www.parkos.it',
                'contact_email' => 'partner@parkos.it',
                'is_active'     => true,
            ],

            [
                'name'          => 'Vologio',
                'slug'          => 'vologio',
                'website'       => 'https://www.vologio.it',
                'contact_email' => 'partner@vologio.it',
                'is_active'     => true,
            ],
            [
                'name'          => 'Sito Web',
                'slug'          => 'website',
                'website'       => null,
                'contact_email' => null,
                'is_active'     => true,
            ],
        ];

        foreach ($platforms as $platform) {
            Platform::updateOrCreate(['slug' => $platform['slug']], $platform);
        }
    }
}