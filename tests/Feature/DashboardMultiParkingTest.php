<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\Platform;
use App\Models\Reservation;
use Carbon\Carbon;

class DashboardMultiParkingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Parking $parkingA;
    private Parking $parkingB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        $this->parkingA = Parking::create([
            'name' => 'Parking A',
            'total_spots' => 100,
            'is_active' => true,
        ]);

        \App\Models\ParkingProduct::create(['parking_id' => $this->parkingA->id, 'name' => 'ProdA', 'code' => 'pa', 'capacity' => 100, 'price' => 10, 'is_active' => true]);

        $this->parkingB = Parking::create([
            'name' => 'Parking B',
            'total_spots' => 50,
            'is_active' => true,
        ]);

        \App\Models\ParkingProduct::create(['parking_id' => $this->parkingB->id, 'name' => 'ProdB', 'code' => 'pb', 'capacity' => 50, 'price' => 10, 'is_active' => true]);
    }

    public function test_dashboard_aggregates_total_spots_for_all_active_parkings()
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        
        $response->assertOk();
        
        // Asserting physical total is 150 (100 + 50)
        $response->assertViewHas('physicalTotal', 150);
    }

    public function test_dashboard_aggregates_reservations_for_all_active_parkings()
    {
        $platform = Platform::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        
        $listingA = ParkingListing::create(['parking_id' => $this->parkingA->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $listingB = ParkingListing::create(['parking_id' => $this->parkingB->id, 'platform_id' => $platform->id, 'is_active' => true]);

        $productA = \App\Models\ParkingProduct::where('parking_id', $this->parkingA->id)->first();
        $productB = \App\Models\ParkingProduct::where('parking_id', $this->parkingB->id)->first();

        Reservation::create([
            'parking_id' => $this->parkingA->id,
            'parking_listing_id' => $listingA->id,
            'parking_product_id' => $productA->id,
            'customer_name' => 'John A',
            'starts_at' => Carbon::today()->setHour(10),
            'ends_at' => Carbon::tomorrow()->setHour(10),
            'spots' => 5,
            'status' => 'confirmed',
        ]);

        Reservation::create([
            'parking_id' => $this->parkingB->id,
            'parking_listing_id' => $listingB->id,
            'parking_product_id' => $productB->id,
            'customer_name' => 'John B',
            'starts_at' => Carbon::today()->setHour(10),
            'ends_at' => Carbon::tomorrow()->setHour(10),
            'spots' => 10,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        
        $response->assertOk();
        
        // Asserting physical occupied is 15 (5 + 10)
        $response->assertViewHas('physicalOccupied', 15);
        
        // Stats should reflect both
        $stats = $response->viewData('stats');
        $this->assertEquals(2, $stats['today_count']);
    }

    public function test_calendar_filters_by_parking_id()
    {
        $response = $this->actingAs($this->admin)->get(route('calendar', ['parking_id' => $this->parkingB->id]));
        
        $response->assertOk();
        
        // Should use parking B
        $response->assertViewHas('parking', function ($viewParking) {
            return $viewParking->id === $this->parkingB->id;
        });
    }

    public function test_calendar_falls_back_to_first_active_parking_if_no_id_provided()
    {
        $response = $this->actingAs($this->admin)->get(route('calendar'));
        
        $response->assertOk();
        
        $response->assertViewHas('parking', function ($viewParking) {
            return $viewParking->id === $this->parkingA->id;
        });
    }

    public function test_calendar_data_filters_by_parking_id()
    {
        $response = $this->actingAs($this->admin)->get(route('calendar.data', ['parking_id' => $this->parkingB->id, 'month' => Carbon::now()->month, 'year' => Carbon::now()->year]));
        
        $response->assertOk();
        // Just checking it resolves correctly without error. The JSON structure will be empty if no reservations exist.
        $response->assertJsonStructure(['reservations', 'from', 'to', 'days']);
    }
}
