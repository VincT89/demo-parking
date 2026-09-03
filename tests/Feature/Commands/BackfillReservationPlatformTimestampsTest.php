<?php

namespace Tests\Feature\Commands;

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\Platform;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillReservationPlatformTimestampsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ignores_fully_populated_reservations_and_does_not_loop()
    {
        $platform = Platform::create(['name' => 'Parkos', 'slug' => 'parkos', 'is_active' => true]);
        $parking = Parking::create(['name' => 'Test', 'total_spots' => 10, 'is_active' => true]);
        $listing = ParkingListing::create(['platform_id' => $platform->id, 'parking_id' => $parking->id, 'is_active' => true]);
        $product = \App\Models\ParkingProduct::create([
            'parking_id' => $parking->id,
            'name' => 'Test',
            'code' => 'TEST',
            'capacity' => 10,
            'price' => 10,
            'is_active' => true,
        ]);

        // A fully populated reservation
        Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'parking_listing_id' => $listing->id,
            'external_id' => 'EXT1',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@test.com',
            'starts_at' => Carbon::now()->addDays(1),
            'ends_at' => Carbon::now()->addDays(2),
            'spots' => 1,
            'price' => 10,
            'status' => 'confirmed',
            'first_seen_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
            'platform_created_at' => Carbon::now(),
            'platform_updated_at' => Carbon::now(),
            // Intentionally omit platform_cancelled_at to ensure the command ignores it correctly
            'raw_data' => []
        ]);

        $this->artisan('reservations:backfill-platform-timestamps')
            ->expectsOutputToContain('Found 0 reservations to process.')
            ->expectsOutputToContain('Backfill complete. Processed: 0, Updated: 0')
            ->assertSuccessful();
    }

    public function test_it_backfills_missing_timestamps()
    {
        $platform = Platform::create(['name' => 'Parkos', 'slug' => 'parkos', 'is_active' => true]);
        $parking = Parking::create(['name' => 'Test', 'total_spots' => 10, 'is_active' => true]);
        $listing = ParkingListing::create(['platform_id' => $platform->id, 'parking_id' => $parking->id, 'is_active' => true]);
        $product = \App\Models\ParkingProduct::create([
            'parking_id' => $parking->id,
            'name' => 'Test',
            'code' => 'TEST',
            'capacity' => 10,
            'price' => 10,
            'is_active' => true,
        ]);

        $createdAt = Carbon::now()->subDays(5);
        $updatedAt = Carbon::now()->subDays(2);
        $platformCreatedAt = Carbon::now()->subDays(6);
        
        $res = new Reservation([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'parking_listing_id' => $listing->id,
            'external_id' => 'EXT2',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@test.com',
            'starts_at' => Carbon::now()->addDays(1),
            'ends_at' => Carbon::now()->addDays(2),
            'spots' => 1,
            'price' => 10,
            'status' => 'confirmed',
            'first_seen_at' => null,
            'last_seen_at' => null,
            'platform_created_at' => null,
            'platform_updated_at' => null,
            'raw_data' => [
                'created_at' => $platformCreatedAt->toDateTimeString(),
            ]
        ]);
        
        $res->timestamps = false;
        $res->created_at = $createdAt;
        $res->updated_at = $updatedAt;
        $res->save();

        $this->artisan('reservations:backfill-platform-timestamps')
            ->expectsOutputToContain('Found 1 reservations to process.')
            ->expectsOutputToContain('Backfill complete. Processed: 1, Updated: 1')
            ->assertSuccessful();

        $res->refresh();
        $this->assertEquals($createdAt->toDateTimeString(), $res->first_seen_at->toDateTimeString());
        $this->assertEquals($updatedAt->toDateTimeString(), $res->last_seen_at->toDateTimeString());
        $this->assertEquals($platformCreatedAt->toDateTimeString(), $res->platform_created_at->toDateTimeString());
    }

    public function test_it_ignores_reservations_with_only_missing_platform_timestamps()
    {
        $platform = Platform::create(['name' => 'Parkos', 'slug' => 'parkos', 'is_active' => true]);
        $parking = Parking::create(['name' => 'Test', 'total_spots' => 10, 'is_active' => true]);
        $listing = ParkingListing::create(['platform_id' => $platform->id, 'parking_id' => $parking->id, 'is_active' => true]);
        $product = \App\Models\ParkingProduct::create([
            'parking_id' => $parking->id,
            'name' => 'Test',
            'code' => 'TEST',
            'capacity' => 10,
            'price' => 10,
            'is_active' => true,
        ]);

        $res = new Reservation([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'parking_listing_id' => $listing->id,
            'external_id' => 'EXT3',
            'customer_name' => 'Jack Doe',
            'customer_email' => 'jack@test.com',
            'starts_at' => Carbon::now()->addDays(1),
            'ends_at' => Carbon::now()->addDays(2),
            'spots' => 1,
            'price' => 10,
            'status' => 'confirmed',
            'first_seen_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
            'platform_created_at' => null,
            'platform_updated_at' => null,
            'raw_data' => []
        ]);
        
        $res->timestamps = false;
        $res->created_at = Carbon::now();
        $res->updated_at = Carbon::now();
        $res->save();

        $this->artisan('reservations:backfill-platform-timestamps')
            ->expectsOutputToContain('Found 0 reservations to process.')
            ->expectsOutputToContain('Backfill complete. Processed: 0, Updated: 0')
            ->assertSuccessful();
    }
}
