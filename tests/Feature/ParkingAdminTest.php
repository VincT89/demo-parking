<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\Reservation;
use App\Models\Platform;
use App\Models\ParkingListing;
use Carbon\Carbon;

class ParkingAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Parking $parking;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        $this->parking = Parking::create([
            'name' => 'Test Parking',
            'total_spots' => 100,
            'is_active' => true,
        ]);
    }

    public function test_accepts_when_capacity_equals_total_spots()
    {
        $payload = [
            'parking_id' => $this->parking->id,
            'name' => 'Main Park',
            'total_spots' => 100,
            'is_active' => true,
            'products' => [
                ['name' => 'Prod 1', 'code' => 'p1', 'capacity' => 60, 'price' => 10, 'is_active' => true],
                ['name' => 'Prod 2', 'code' => 'p2', 'capacity' => 40, 'price' => 12, 'is_active' => true],
            ]
        ];

        $response = $this->actingAs($this->admin)->put(route('parkings.products.upsert', $this->parking), $payload);
        
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('parking_products', ['code' => 'p1', 'capacity' => 60]);
    }

    public function test_rejects_when_capacity_exceeds_total_spots()
    {
        $payload = [
            'parking_id' => $this->parking->id,
            'name' => 'Main Park',
            'total_spots' => 100,
            'is_active' => true,
            'products' => [
                ['name' => 'Prod 1', 'code' => 'p1', 'capacity' => 80, 'price' => 10, 'is_active' => true],
                ['name' => 'Prod 2', 'code' => 'p2', 'capacity' => 30, 'price' => 12, 'is_active' => true],
            ]
        ];

        $response = $this->actingAs($this->admin)->put(route('parkings.products.upsert', $this->parking), $payload);
        
        $response->assertSessionHasErrors(['general']);
    }

    public function test_accepts_capacity_exceeds_if_some_are_inactive()
    {
        $payload = [
            'parking_id' => $this->parking->id,
            'name' => 'Main Park',
            'total_spots' => 100,
            'is_active' => true,
            'products' => [
                ['name' => 'Prod 1', 'code' => 'p1', 'capacity' => 80, 'price' => 10, 'is_active' => true],
                ['name' => 'Prod 2', 'code' => 'p2', 'capacity' => 80, 'price' => 12, 'is_active' => false], // Totale apparente 160 ma 80 disattivo
            ]
        ];

        $response = $this->actingAs($this->admin)->put(route('parkings.products.upsert', $this->parking), $payload);
        
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('parking_products', ['code' => 'p2', 'is_active' => false]);
    }

    public function test_rejects_duplicate_codes_in_payload()
    {
        $payload = [
            'parking_id' => $this->parking->id,
            'name' => 'Main Park',
            'total_spots' => 100,
            'is_active' => true,
            'products' => [
                ['name' => 'Prod A', 'code' => 'same_code', 'capacity' => 10, 'price' => 10, 'is_active' => true],
                ['name' => 'Prod B', 'code' => 'same_code', 'capacity' => 10, 'price' => 10, 'is_active' => true],
            ]
        ];

        $response = $this->actingAs($this->admin)->put(route('parkings.products.upsert', $this->parking), $payload);
        
        $response->assertSessionHasErrors(['products.0.code']);
    }

    public function test_rejects_hard_delete_if_product_has_history()
    {
        $product = ParkingProduct::create([
            'parking_id' => $this->parking->id,
            'name' => 'Legacy Prod',
            'code' => 'legacy',
            'capacity' => 50,
            'price' => 5,
            'is_active' => true
        ]);

        $platform = new Platform(['name' => 'Test']);
        $platform->slug = 'test';
        $platform->save();
        
        $listing = ParkingListing::create([
            'parking_id' => $this->parking->id,
            'platform_id' => $platform->id,
            'name' => 'Listing Test',
            'external_id' => '123'
        ]);

        Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_listing_id' => $listing->id,
            'parking_product_id' => $product->id, // Legame storico!
            'external_id' => 'RES123',
            'customer_name' => 'John Doe',
            'starts_at' => Carbon::now()->subDays(2),
            'ends_at' => Carbon::now()->subDay(),
            'spots' => 1,
            'price' => 10
        ]);

        $payload = [
            'parking_id' => $this->parking->id,
            'name' => 'Main Park',
            'total_spots' => 100,
            'is_active' => true,
            'products' => [
                ['id' => $product->id, 'name' => 'Legacy Prod', 'code' => 'legacy', 'delete' => true], // Proviamo ad eliminarlo!
            ]
        ];

        $response = $this->actingAs($this->admin)->put(route('parkings.products.upsert', $this->parking), $payload);

        $response->assertSessionHasErrors(['products.0.delete']);
        $this->assertDatabaseHas('parking_products', ['id' => $product->id]); // Deve ancora esistere
    }

    public function test_rejects_capacity_reduction_below_future_overlap()
    {
        $product = ParkingProduct::create([
            'parking_id' => $this->parking->id,
            'name' => 'Future Prod',
            'code' => 'future',
            'capacity' => 50,
            'price' => 5,
            'is_active' => true
        ]);

        $platform = new Platform(['name' => 'Test']);
        $platform->slug = 'test';
        $platform->save();

        $listing = ParkingListing::create([
            'parking_id' => $this->parking->id,
            'platform_id' => $platform->id,
            'name' => 'Listing Test 2',
            'external_id' => '456'
        ]);

        // Occupa 40 posti domani
        Reservation::create([
            'parking_id' => $this->parking->id,
            'parking_listing_id' => $listing->id,
            'parking_product_id' => $product->id,
            'external_id' => 'RES456',
            'customer_name' => 'Jane Doe',
            'starts_at' => Carbon::tomorrow()->setHour(10),
            'ends_at' => Carbon::tomorrow()->addDay()->setHour(10),
            'spots' => 40,
            'price' => 100
        ]);

        $payload = [
            'parking_id' => $this->parking->id,
            'name' => 'Main Park',
            'total_spots' => 100,
            'is_active' => true,
            'products' => [
                ['id' => $product->id, 'name' => 'Future Prod', 'code' => 'future', 'capacity' => 30, 'price' => 5, 'is_active' => true], // Riduciamo a 30!
            ]
        ];

        $response = $this->actingAs($this->admin)->put(route('parkings.products.upsert', $this->parking), $payload);

        // Deve fallire sull'attributo capacity per colpa del check overbooking
        $response->assertSessionHasErrors(['products.0.capacity']); 
    }
}
