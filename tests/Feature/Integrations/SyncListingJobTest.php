<?php

namespace Tests\Feature\Integrations;

use App\Jobs\SyncListingJob;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use App\Actions\SyncListingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class SyncListingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_job_logs_and_throws_on_fatal_error(): void
    {
        $platform = Platform::create([
            'name' => 'Parkos',
            'slug' => 'parkos',
            'is_active' => true,
        ]);

        $parking = Parking::create([
            'name' => 'Test Parking',
            'total_spots' => 100,
            'is_active' => true,
        ]);

        $listing = ParkingListing::create([
            'platform_id' => $platform->id,
            'parking_id' => $parking->id,
            'external_id' => '1895',
            'is_active' => true,
        ]);

        $product = ParkingProduct::create([
            'parking_id' => $parking->id,
            'name' => 'Open Air',
            'code' => 'OPEN_AIR_INT',
            'capacity' => 10,
            'price' => 10,
            'is_active' => true,
        ]);

        PlatformProductMapping::create([
            'platform_id' => $platform->id,
            'parking_product_id' => $product->id,
            'external_ref' => 'OPEN_AIR',
            'is_active' => true,
        ]);

        $actionMock = \Mockery::mock(SyncListingAction::class);
        $actionMock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'created' => 0,
                'updated' => 0,
                'failed' => 1,
                'skipped' => 0,
                'errors' => ['Fatal Error: qualcosa è andato storto'],
            ]);

        $job = new SyncListingJob($listing);

        try {
            $job->handle($actionMock);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Fatal Error:', $e->getMessage());
        }

        $this->assertDatabaseHas('sync_logs', [
            'parking_listing_id' => $listing->id,
            'source' => 'job',
            'status' => 'failed',
            'reservations_failed' => 1,
        ]);
    }

    public function test_sync_log_notes_include_skipped_reasons_without_failed_status(): void
    {
        $platform = Platform::create([
            'name' => 'Parkos',
            'slug' => 'parkos',
            'is_active' => true,
        ]);

        $parking = Parking::create([
            'name' => 'Test Parking',
            'total_spots' => 100,
            'is_active' => true,
        ]);

        $listing = ParkingListing::create([
            'platform_id' => $platform->id,
            'parking_id' => $parking->id,
            'external_id' => '1895',
            'is_active' => true,
        ]);

        $actionMock = \Mockery::mock(SyncListingAction::class);
        $actionMock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'created' => 0,
                'updated' => 0,
                'failed' => 0,
                'skipped' => 1,
                'errors' => [],
                'warnings' => [],
                'skipped_reasons' => ['Prenotazione cancellata in origine e non presente a sistema.'],
            ]);

        $job = new SyncListingJob($listing);
        $job->handle($actionMock);

        $this->assertDatabaseHas('sync_logs', [
            'parking_listing_id' => $listing->id,
            'source' => 'job',
            'status' => 'success',
            'reservations_skipped' => 1,
            'reservations_failed' => 0,
        ]);

        $log = \App\Models\SyncLog::where('parking_listing_id', $listing->id)->first();
        $this->assertNotNull($log->notes);
        $this->assertStringContainsString('Prenotazione cancellata in origine e non presente a sistema.', $log->notes);
    }
}
