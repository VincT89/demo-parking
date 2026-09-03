<?php

namespace Tests\Feature;

use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarDayViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disabilita la gestione delle eccezioni per il setup per evitare side-effects
    }

    private function createStaff()
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function createAdmin()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get(route('calendar.day'));
        $response->assertRedirect('/login');
    }

    public function test_admin_and_staff_can_access()
    {
        $staff = $this->createStaff();
        $admin = $this->createAdmin();

        $this->actingAs($staff)->get(route('calendar.day'))->assertOk();
        $this->actingAs($admin)->get(route('calendar.day'))->assertOk();
    }

    public function test_invalid_type_returns_404()
    {
        $staff = $this->createStaff();

        $this->actingAs($staff)
             ->get(route('calendar.day', ['type' => 'invalid']))
             ->assertNotFound();
    }

    public function test_entries_view_filters_by_starts_at_and_orders_by_time()
    {
        $staff = $this->createStaff();
        $today = Carbon::today();

        $parking = Parking::create(['name' => 'Test', 'capacity_mode' => 'shared', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod', 'code' => 'p1', 'capacity' => 10, 'is_active' => true, 'price' => 10.0]);
        $platform = \App\Models\Platform::create(['name' => 'TestPlatform', 'slug' => 'test-platform', 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'external_id' => '123', 'is_active' => true]);

        // Reservation: entra oggi alle 10
        $res1 = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(10),
            'ends_at' => $today->copy()->addDays(2),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        // Reservation: entra oggi alle 08
        $res2 = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(8),
            'ends_at' => $today->copy()->addDays(2),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        // Reservation: entra domani
        $res3 = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->addDay()->setHour(10),
            'ends_at' => $today->copy()->addDays(3),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        $response = $this->actingAs($staff)
                         ->get(route('calendar.day', ['type' => 'entries', 'date' => $today->toDateString()]));

        $response->assertOk();
        
        $reservations = $response->viewData('reservations');

        // Dovrebbe contenere solo res1 e res2
        $this->assertCount(2, $reservations);
        
        // Ordinate per starts_at (08:00 prima di 10:00)
        $this->assertEquals($res2->id, $reservations[0]->id);
        $this->assertEquals($res1->id, $reservations[1]->id);
    }

    public function test_exits_view_filters_by_ends_at_and_orders_by_time()
    {
        $staff = $this->createStaff();
        $today = Carbon::today();

        $parking = Parking::create(['name' => 'Test', 'capacity_mode' => 'shared', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod', 'code' => 'p1', 'capacity' => 10, 'is_active' => true, 'price' => 10.0]);
        $platform = \App\Models\Platform::create(['name' => 'TestPlatform', 'slug' => 'test-platform', 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'external_id' => '123', 'is_active' => true]);

        // Reservation: esce oggi alle 15
        $res1 = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->subDays(2),
            'ends_at' => $today->copy()->setHour(15),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        // Reservation: esce oggi alle 12
        $res2 = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->subDays(2),
            'ends_at' => $today->copy()->setHour(12),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        // Reservation: esce domani
        $res3 = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->subDays(2),
            'ends_at' => $today->copy()->addDay()->setHour(10),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        $response = $this->actingAs($staff)
                         ->get(route('calendar.day', ['type' => 'exits', 'date' => $today->toDateString()]));

        $response->assertOk();
        
        $reservations = $response->viewData('reservations');

        // Dovrebbe contenere solo res1 e res2
        $this->assertCount(2, $reservations);
        
        // Ordinate per ends_at (12:00 prima di 15:00)
        $this->assertEquals($res2->id, $reservations[0]->id);
        $this->assertEquals($res1->id, $reservations[1]->id);
    }

    public function test_excludes_cancelled_expired_and_expired_pending_reservations()
    {
        $staff = $this->createStaff();
        $today = Carbon::today();

        $parking = Parking::create(['name' => 'Test', 'capacity_mode' => 'shared', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod', 'code' => 'p1', 'capacity' => 10, 'is_active' => true, 'price' => 10.0]);
        $platform = \App\Models\Platform::create(['name' => 'TestPlatform', 'slug' => 'test-platform', 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'external_id' => '123', 'is_active' => true]);

        // Valid confirmed
        Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(9),
            'ends_at' => $today->copy()->addDays(2),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        // Valid active pending (not expired)
        Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(10),
            'ends_at' => $today->copy()->addDays(2),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'pending',
            'expires_at' => now()->addMinutes(30)
        ]);

        // Invalid cancelled
        Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(11),
            'ends_at' => $today->copy()->addDays(2),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'cancelled'
        ]);

        // Invalid expired pending
        Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(12),
            'ends_at' => $today->copy()->addDays(2),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Test Customer', 'spots' => 1, 'price' => 10.0, 'status' => 'pending',
            'expires_at' => now()->subMinutes(30)
        ]);

        $response = $this->actingAs($staff)
                         ->get(route('calendar.day', ['type' => 'entries', 'date' => $today->toDateString()]));

        $response->assertOk();
        
        $reservations = $response->viewData('reservations');

        // Should only see the confirmed and the non-expired pending
        $this->assertCount(2, $reservations);
    }

    public function test_reservation_spanning_multiple_days_appears_in_both_views_on_correct_days()
    {
        $staff = $this->createStaff();
        $today = Carbon::today(); // e.g. 2026-04-24 00:00:00

        $parking = Parking::create(['name' => 'Test', 'capacity_mode' => 'shared', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Prod', 'code' => 'p1', 'capacity' => 10, 'is_active' => true, 'price' => 10.0]);
        $platform = \App\Models\Platform::create(['name' => 'TestPlatform', 'slug' => 'test-platform', 'is_active' => true]);
        $listing = \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'external_id' => '123', 'is_active' => true]);

        // Reservation: entra oggi alle 23:00, esce domani alle 01:00
        $res = Reservation::create([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'starts_at' => $today->copy()->setHour(23),
            'ends_at' => $today->copy()->addDay()->setHour(1),
            'parking_listing_id' => $listing->id,
            'customer_name' => 'Night Owl', 'spots' => 1, 'price' => 10.0, 'status' => 'confirmed'
        ]);

        // Check entrate di oggi
        $responseEntriesToday = $this->actingAs($staff)
            ->get(route('calendar.day', ['type' => 'entries', 'date' => $today->toDateString()]));
        $responseEntriesToday->assertOk();
        $this->assertCount(1, $responseEntriesToday->viewData('reservations'));
        $this->assertEquals($res->id, $responseEntriesToday->viewData('reservations')->first()->id);

        // Check uscite di domani
        $responseExitsTomorrow = $this->actingAs($staff)
            ->get(route('calendar.day', ['type' => 'exits', 'date' => $today->copy()->addDay()->toDateString()]));
        $responseExitsTomorrow->assertOk();
        $this->assertCount(1, $responseExitsTomorrow->viewData('reservations'));
        $this->assertEquals($res->id, $responseExitsTomorrow->viewData('reservations')->first()->id);

        // Check uscite di oggi (non ci deve essere)
        $responseExitsToday = $this->actingAs($staff)
            ->get(route('calendar.day', ['type' => 'exits', 'date' => $today->toDateString()]));
        $responseExitsToday->assertOk();
        $this->assertCount(0, $responseExitsToday->viewData('reservations'));
    }
}
