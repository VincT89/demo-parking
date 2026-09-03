<?php

namespace Tests\Feature\Integrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Platform;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\PlatformProductMapping;
use Illuminate\Support\Facades\Config;

class SyncPlatformsCommandTest extends TestCase
{
    use RefreshDatabase;

    private ParkingListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Platform::create(['name' => 'Parkos', 'slug' => 'parkos', 'is_active' => true]);
        $parking = Parking::create(['name' => 'Test Parking', 'total_spots' => 100, 'is_active' => true]);
        
        $this->listing = ParkingListing::create([
            'platform_id' => $platform->id,
            'parking_id' => $parking->id,
            'is_active' => true
        ]);

        $product = ParkingProduct::create([
            'parking_id' => $parking->id,
            'name' => 'Open Air',
            'code' => 'OPEN_AIR_INT',
            'capacity' => 10,
            'price' => 10,
            'is_active' => true
        ]);

        PlatformProductMapping::create([
            'platform_id' => $platform->id,
            'parking_product_id' => $product->id,
            'external_ref' => '15325:shuttle:outdoor',
            'is_active' => true
        ]);

        Config::set('services.parkos.fixture_mode', true);
    }

    public function test_command_executes_dry_run_and_logs_results()
    {
        Config::set('services.parkos.fixture_file', 'reservations_success.json');

        $this->artisan('platforms:sync', [
            '--platform' => 'parkos',
            '--dry-run' => true,
        ])
             ->expectsOutputToContain('Starting sync')
             ->assertSuccessful();

        $this->assertDatabaseHas('sync_logs', [
            'parking_listing_id' => $this->listing->id,
            'source' => 'command',
            'status' => 'success',
            'is_dry_run' => true,
            'reservations_created' => 1,
            'reservations_updated' => 0,
            'reservations_skipped' => 0,
            'reservations_failed' => 0,
        ]);
        
        // Assert no reservation was created
        $this->assertDatabaseEmpty('reservations');
    }

    public function test_command_executes_real_run_and_logs_results()
    {
        Config::set('services.parkos.fixture_file', 'reservations_success.json');

        $this->artisan('platforms:sync', [
            '--platform' => 'parkos'
        ])
             ->assertSuccessful();

        $this->assertDatabaseHas('sync_logs', [
            'parking_listing_id' => $this->listing->id,
            'source' => 'command',
            'status' => 'success',
            'is_dry_run' => false,
            'reservations_created' => 1,
            'reservations_updated' => 0,
        ]);
        
        // Assert reservation was created
        $this->assertDatabaseCount('reservations', 1);
    }
}
