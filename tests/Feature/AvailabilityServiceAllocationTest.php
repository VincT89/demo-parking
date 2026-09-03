<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\ParkingCapacityAllocation;
use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use App\Models\Platform;
use App\Models\ParkingListing;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use App\Enums\UserRole;

class AvailabilityServiceAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disabilitiamo eventi che mandano mail o simili
        \Illuminate\Support\Facades\Event::fake();
    }

    public function test_staff_cannot_create_allocations()
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $parking = Parking::create(['name' => 'P1', 'total_spots' => 100, 'is_active' => true]);

        $response = $this->actingAs($staff)->post(route('parkings.allocations.store', $parking), [
            'allocation_type' => 'rentcar',
            'spots' => 10,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addDays(5)->toDateTimeString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_allocations()
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $parking = Parking::create(['name' => 'P1', 'total_spots' => 100, 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('parkings.allocations.store', $parking), [
            'allocation_type' => 'rentcar',
            'spots' => 10,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addDays(5)->toDateTimeString(),
            'notes' => 'Test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('parking_capacity_allocations', [
            'parking_id' => $parking->id,
            'allocation_type' => 'rentcar',
            'spots' => 10,
            'notes' => 'Test',
        ]);
    }

    public function test_allocation_reduces_availability()
    {
        $parking = Parking::create(['name' => 'P1', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Auto', 'code' => 'auto', 'capacity' => 100, 'price' => 10, 'is_active' => true]);

        $start = Carbon::tomorrow()->setHour(10);
        $end = Carbon::tomorrow()->addDays(2)->setHour(10);

        // Alloco 30 posti per tutto il parcheggio in quel periodo
        ParkingCapacityAllocation::create([
            'parking_id' => $parking->id,
            'allocation_type' => 'rentcar',
            'spots' => 30,
            'starts_at' => $start->copy()->subDay(),
            'ends_at' => $end->copy()->addDay(),
            'is_active' => true,
        ]);

        $service = new AvailabilityService();
        $result = $service->checkProductCapacity($product, $start, $end, 1);

        $this->assertTrue($result->available);
        // 100 totali - 30 allocati = 70 disponibili
        $this->assertEquals(70, $result->availableSpots);
    }

    public function test_allocation_out_of_bounds_does_not_reduce_availability()
    {
        $parking = Parking::create(['name' => 'P1', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Auto', 'code' => 'auto', 'capacity' => 100, 'price' => 10, 'is_active' => true]);

        $start = Carbon::tomorrow()->setHour(10);
        $end = Carbon::tomorrow()->addDays(2)->setHour(10);

        // Alloco 30 posti ma in un periodo PRECEDENTE
        ParkingCapacityAllocation::create([
            'parking_id' => $parking->id,
            'allocation_type' => 'rentcar',
            'spots' => 30,
            'starts_at' => $start->copy()->subDays(10),
            'ends_at' => $start->copy()->subDays(5),
            'is_active' => true,
        ]);

        $service = new AvailabilityService();
        $result = $service->checkProductCapacity($product, $start, $end, 1);

        $this->assertTrue($result->available);
        // Nessun overlap, disponibilità piena
        $this->assertEquals(100, $result->availableSpots);
    }

    public function test_allocation_plus_block_plus_reservation_never_goes_below_zero()
    {
        $parking = Parking::create(['name' => 'P1', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Auto', 'code' => 'auto', 'capacity' => 100, 'price' => 10, 'is_active' => true]);
        
        $platform = Platform::create(['name' => 'Web', 'slug' => 'web', 'is_active' => true]);
        $listing = ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);

        $start = Carbon::tomorrow()->setHour(10);
        $end = Carbon::tomorrow()->addDays(2)->setHour(10);

        // 1. Alloco 40 posti (rentcar)
        ParkingCapacityAllocation::create([
            'parking_id' => $parking->id,
            'allocation_type' => 'rentcar',
            'spots' => 40,
            'starts_at' => $start->copy()->subDay(),
            'ends_at' => $end->copy()->addDay(),
            'is_active' => true,
        ]);

        // 2. Blocco 30 posti per lavori
        AvailabilityBlock::create([
            'parking_id' => $parking->id,
            'spots' => 30,
            'starts_at' => $start->copy()->subDay(),
            'ends_at' => $end->copy()->addDay(),
            'notes' => 'Lavori',
            'is_active' => true,
        ]);

        // 3. Prenoto 40 posti (questo dovrebbe tecnicamente sforare i 100 totali)
        // Setto uuid finto e un po' di info base
        Reservation::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'parking_listing_id' => $listing->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'spots' => 40,
            'status' => \App\Enums\ReservationStatus::Confirmed->value,
            'customer_name' => 'Test',
            'customer_email' => 'test@test.com',
            'customer_phone' => '123',
            'vehicle_plate' => 'AB123CD',
            'total_price' => 10,
        ]);

        $service = new AvailabilityService();
        $result = $service->checkProductCapacity($product, $start, $end, 1);

        // 100 - 40 - 30 - 40 = -10, ma limitato a 0
        $this->assertFalse($result->available);
        $this->assertEquals(0, $result->availableSpots);
    }

    public function test_allocation_exceeding_capacity_returns_zero()
    {
        $parking = Parking::create(['name' => 'P1', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Auto', 'code' => 'auto', 'capacity' => 10, 'price' => 10, 'is_active' => true]);

        $start = Carbon::tomorrow()->setHour(10);
        $end = Carbon::tomorrow()->addDays(2)->setHour(10);

        // Alloco 15 posti quando la capacità è solo 10
        ParkingCapacityAllocation::create([
            'parking_id' => $parking->id,
            'allocation_type' => 'rentcar',
            'spots' => 15,
            'starts_at' => $start->copy()->subDay(),
            'ends_at' => $end->copy()->addDay(),
            'is_active' => true,
        ]);

        $service = new AvailabilityService();
        $result = $service->checkProductCapacity($product, $start, $end, 1);

        $this->assertFalse($result->available);
        $this->assertEquals(0, $result->availableSpots);
    }
}
