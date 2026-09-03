<?php

namespace Tests\Feature\Integrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Platform;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\PlatformProductMapping;
use App\Models\Reservation;
use App\Actions\SyncListingAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class SyncListingActionTest extends TestCase
{
    use RefreshDatabase;

    private ParkingListing $listing;
    private SyncListingAction $action;

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

        $this->action = app(SyncListingAction::class);
    }

    public function test_sync_creates_reservation()
    {
        Config::set('services.parkos.fixture_file', 'reservations_success.json');

        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['updated']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['skipped']);
        $this->assertEmpty($stats['errors']);

        $this->assertDatabaseHas('reservations', [
            'parking_listing_id' => $this->listing->id,
            'external_id' => 'AA11BB22',
            'customer_name' => 'Mario Rossi',
            'platform_created_at' => '2026-06-14 12:00:00',
            'platform_updated_at' => '2026-06-14 12:05:00',
        ]);
    }

    public function test_sync_updates_existing_reservation()
    {
        Config::set('services.parkos.fixture_file', 'reservations_success.json');

        // First execution creates it
        $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        $reservation = Reservation::where('external_id', 'AA11BB22')->first();
        $this->assertNotNull($reservation->first_seen_at);
        $this->assertNotNull($reservation->last_seen_at);

        $firstSeenAt = $reservation->first_seen_at;

        sleep(1);

        // Second execution should update it
        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['updated']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['skipped']);

        $reservation->refresh();
        $this->assertEquals(
            $firstSeenAt->toDateTimeString(),
            $reservation->first_seen_at->toDateTimeString()
        );
        $this->assertTrue($reservation->last_seen_at->greaterThan($firstSeenAt));
    }

    public function test_sync_dry_run_identifies_created_and_updated()
    {
        Config::set('services.parkos.fixture_file', 'reservations_success.json');

        // Dry run first
        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), true);
        
        $this->assertEquals(1, $stats['created']);
        $this->assertEquals(0, $stats['updated']);
        
        // Assert nothing was actually saved
        $this->assertDatabaseMissing('reservations', ['external_id' => 'AA11BB22']);

        // Save it for real
        $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        // Dry run again
        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), true);
        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(1, $stats['updated']);
    }

    public function test_sync_fails_on_unmapped_product()
    {
        Config::set('services.parkos.fixture_file', 'reservations_unmapped_product.json');

        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(0, $stats['updated']);
        $this->assertEquals(0, $stats['skipped']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertCount(1, $stats['errors']);
        $this->assertStringContainsString('Nessun mapping attivo trovato', $stats['errors'][0]);
    }

    public function test_sync_captures_invalid_shape_exception()
    {
        Config::set('services.parkos.fixture_file', 'reservations_bad_shape.json');

        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        // The adapter throws an exception during fetch, which should be caught globally by the action
        $this->assertEquals(1, $stats['failed']);
        $this->assertCount(1, $stats['errors']);
        $this->assertStringContainsString('Missing required field', $stats['errors'][0]);
    }

    public function test_sync_creates_and_cancels_reservation()
    {
        // Create the reservation manually first so it exists to be cancelled
        Reservation::create([
            'parking_id' => $this->listing->parking_id,
            'parking_listing_id' => $this->listing->id,
            'parking_product_id' => ParkingProduct::first()->id,
            'external_id' => 'PKS-CANCELLED-1',
            'customer_name' => 'Mario Rossi',
            'starts_at' => '2026-06-15 10:00:00',
            'ends_at' => '2026-06-20 18:00:00',
            'status' => 'confirmed'
        ]);

        Config::set('services.parkos.fixture_file', 'reservations_cancelled.json');

        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        $this->assertEquals(1, $stats['updated']); // Updated because it was cancelled

        $reservation = Reservation::where('external_id', 'PKS-CANCELLED-1')->first();
        $this->assertNotNull($reservation);
        $this->assertEquals('cancelled', $reservation->status->value);
        $this->assertEquals('2026-06-15 12:30:00', $reservation->platform_cancelled_at->toDateTimeString());
        $this->assertNotNull($reservation->last_seen_at);
    }

    public function test_missing_local_cancelled_reservation_is_skipped_without_marking_sync_as_failed()
    {
        // Don't create the reservation locally
        Config::set('services.parkos.fixture_file', 'reservations_cancelled.json');

        $stats = $this->action->execute($this->listing, Carbon::today(), Carbon::tomorrow(), false);

        $this->assertEquals(0, $stats['created']);
        $this->assertEquals(0, $stats['updated']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(1, $stats['skipped']);
        $this->assertEmpty($stats['errors']);
        $this->assertCount(1, $stats['skipped_reasons']);
        $this->assertStringContainsString('Prenotazione cancellata in origine e non presente a sistema.', $stats['skipped_reasons'][0]);
    }
}
