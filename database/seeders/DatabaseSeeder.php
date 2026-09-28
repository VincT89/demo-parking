<?php

namespace Database\Seeders;

use App\Models\Management;
use App\Models\Parking;
use App\Models\User;
use App\Tenancy\ParkingContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Never erase an installed environment or existing management memberships.
        if (Parking::withoutGlobalScopes()->exists() || User::exists()) {
            throw new \RuntimeException('DatabaseSeeder requires an empty database. Existing data was not changed.');
        }
        $management = Management::where('is_initial', true)->firstOrFail();
        $context = app(ParkingContext::class);
        $context->select(null, $management);
        try {
            $this->call(ParkingSeeder::class);
            $parking = Parking::withoutGlobalScopes()->where('management_id', $management->id)->firstOrFail();
            $context->select($parking);
            $parking->forceFill(['is_default_public' => true])->save();
            $this->call([
                ParkingProductsSeeder::class, PlatformSeeder::class,
                ParkingListingSeeder::class, PlatformProductMappingSeeder::class, ParkosMappingSeeder::class,
            ]);
            if (config('demo.enabled')) {
                $this->call(DemoScenarioSeeder::class);
            } else {
                $this->call(UserSeeder::class);
            }
            foreach (User::all() as $user) {
                $management->users()->syncWithoutDetaching([$user->id => ['role' => $user->role?->value ?? 'staff']]);
            }
        } finally {
            $context->clear();
        }
        if (config('demo.enabled')) {
            $this->call(DemoManagementSeeder::class);
        }
        $this->call(CustomerSeeder::class);
    }
}
