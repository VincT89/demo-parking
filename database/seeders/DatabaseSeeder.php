<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        // Disabilita i controlli delle chiavi esterne
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        // Pulisce le tabelle (ordine rispettando le FK, anche se disabilitate è buona pratica)
        \App\Models\ElectronicInvoice::truncate();
        \App\Models\ParkingSetting::truncate();
        \App\Models\Payment::truncate();
        \App\Models\Reservation::truncate();
        \App\Models\AvailabilityBlock::truncate();
        \App\Models\ParkingCapacityAllocation::truncate();
        \Illuminate\Support\Facades\DB::table('platform_product_mappings')->truncate();
        \App\Models\ParkingProduct::truncate();
        \App\Models\ParkingListing::truncate();
        \App\Models\SyncLog::truncate();
        \App\Models\Platform::truncate();
        \App\Models\Parking::truncate();
        \App\Models\User::truncate();

        // Riabilita i controlli delle chiavi esterne
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        $this->call([
            UserSeeder::class,
            ParkingSeeder::class,
            ParkingProductsSeeder::class,
            PlatformSeeder::class,
            ParkingListingSeeder::class,
            PlatformProductMappingSeeder::class,
            ParkosMappingSeeder::class,
        ]);

        if (config('demo.enabled')) {
            $this->call(DemoScenarioSeeder::class);
        }
    }
}
