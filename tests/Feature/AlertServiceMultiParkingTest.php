<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Parking;
use App\Models\Platform;
use App\Models\ParkingListing;
use App\Models\Reservation;
use App\Services\AlertService;
use Carbon\Carbon;

class AlertServiceMultiParkingTest extends TestCase
{
    use RefreshDatabase;

    private Parking $parkingA;
    private Parking $parkingB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->parkingA = Parking::create([
            'name' => 'Parking A',
            'total_spots' => 100,
            'is_active' => true,
        ]);

        $this->parkingB = Parking::create([
            'name' => 'Parking B',
            'total_spots' => 50,
            'is_active' => true,
        ]);
    }

    public function test_get_alerts_for_parkings_aggregates_alerts()
    {
        $platform = Platform::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        
        $listingA = ParkingListing::create(['parking_id' => $this->parkingA->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $listingB = ParkingListing::create(['parking_id' => $this->parkingB->id, 'platform_id' => $platform->id, 'is_active' => true]);

        $productA = \App\Models\ParkingProduct::create(['parking_id' => $this->parkingA->id, 'name' => 'ProdA', 'code' => 'proda', 'capacity' => 10, 'price' => 10, 'is_active' => true]);
        $productB = \App\Models\ParkingProduct::create(['parking_id' => $this->parkingB->id, 'name' => 'ProdB', 'code' => 'prodb', 'capacity' => 10, 'price' => 10, 'is_active' => true]);

        // Cancelled reservation for Parking A
        Reservation::create([
            'parking_id' => $this->parkingA->id,
            'parking_listing_id' => $listingA->id,
            'parking_product_id' => $productA->id,
            'customer_name' => 'John A',
            'starts_at' => Carbon::today()->setHour(10),
            'ends_at' => Carbon::tomorrow()->setHour(10),
            'spots' => 5,
            'status' => 'cancelled', // Triggers cancellation alert if >= 3
        ]);
        Reservation::create([
            'parking_id' => $this->parkingA->id,
            'parking_listing_id' => $listingA->id,
            'parking_product_id' => $productA->id,
            'customer_name' => 'John A2',
            'starts_at' => Carbon::today()->setHour(10),
            'ends_at' => Carbon::tomorrow()->setHour(10),
            'spots' => 5,
            'status' => 'cancelled', 
        ]);
        Reservation::create([
            'parking_id' => $this->parkingB->id,
            'parking_listing_id' => $listingB->id,
            'parking_product_id' => $productB->id,
            'customer_name' => 'John B',
            'starts_at' => Carbon::today()->setHour(10),
            'ends_at' => Carbon::tomorrow()->setHour(10),
            'spots' => 10,
            'status' => 'cancelled', 
        ]);

        $service = new AlertService();
        $alerts = $service->getAlertsForParkings(Parking::where('is_active', true)->get());

        // We assume it returns an array of alert structures.
        $this->assertNotEmpty($alerts);
        
        // Find the cancellation alert
        $cancellationAlerts = array_filter($alerts, fn($a) => str_contains($a['message'], 'cancellazioni ricevute oggi su'));
        $this->assertNotEmpty($cancellationAlerts);
    }
}
