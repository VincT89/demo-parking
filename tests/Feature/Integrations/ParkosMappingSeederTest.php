<?php

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use Database\Seeders\ParkosMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->parkos = Platform::create(['name' => 'Parkos', 'slug' => 'parkos', 'is_active' => true]);
    $this->parking = Parking::create(['name' => 'Parcheggio Centrale', 'timezone' => 'Europe/Rome', 'total_spots' => 100]);
    $this->autoOpen = ParkingProduct::create([
        'code' => 'auto_open',
        'name' => 'Auto Scoperto',
        'is_active' => true,
        'parking_id' => $this->parking->id,
        'capacity' => 100,
        'price' => 10.00,
    ]);
});

test('test_parkos_mapping_seeder_creates_shuttle_outdoor_mapping', function () {
    $this->seed(ParkosMappingSeeder::class);

    $this->assertDatabaseHas('platform_product_mappings', [
        'platform_id' => $this->parkos->id,
        'external_ref' => '1895:shuttle:outdoor',
        'parking_product_id' => $this->autoOpen->id,
        'is_active' => 1,
    ]);
});

test('test_parkos_mapping_seeder_creates_shuttle_indoor_mapping_to_auto_open', function () {
    $this->seed(ParkosMappingSeeder::class);

    $this->assertDatabaseHas('platform_product_mappings', [
        'platform_id' => $this->parkos->id,
        'external_ref' => '1895:shuttle:indoor',
        'parking_product_id' => $this->autoOpen->id,
        'is_active' => 1,
    ]);
});

test('test_parkos_mapping_seeder_is_idempotent', function () {
    $this->seed(ParkosMappingSeeder::class);
    $this->seed(ParkosMappingSeeder::class);

    $count = PlatformProductMapping::where('platform_id', $this->parkos->id)
        ->where('external_ref', '1895:shuttle:indoor')
        ->count();

    expect($count)->toBe(1);
});
