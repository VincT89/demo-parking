<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Parking;
use App\Models\ParkingProduct;
use Carbon\Carbon;
use App\Models\Reservation;
use App\Models\Platform;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_public_form()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 10, 'price' => 20, 'is_active' => true]);
        
        $response = $this->get(route('public.booking.form'));

        $response->assertStatus(200);
        $response->assertSee('Standard');
    }

    public function test_can_check_availability_via_ajax()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 10, 'price' => 20, 'is_active' => true]);

        $response = $this->postJson(route('public.booking.check'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
            'total_price' => 120 // 2 spots * 3 days * 20 price
        ]);
    }

    public function test_can_store_reservation()
    {
        $platform = Platform::create(['name' => 'Sito Web', 'slug' => 'website', 'is_active' => true]);
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 100, 'is_active' => true]);
        \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 10, 'price' => 20, 'is_active' => true]);

        $response = $this->post(route('public.booking.store'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 2,
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.com',
            'customer_phone' => '1234567890',
            'license_plate' => 'AB123CD',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('reservations', [
            'customer_name' => 'Mario Rossi',
            'spots' => 2,
            'status' => 'pending',
            'price' => 120,
        ]);
        
        $reservation = Reservation::first();
        $response->assertRedirect(route('public.booking.payment', $reservation->external_id));
        
        // Assert source logic is saved in raw_data
        $this->assertEquals('website', $reservation->raw_data['source']);
    }

    public function test_spots_validation_rejects_zero_or_negative()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 10, 'price' => 20, 'is_active' => true]);

        $platform = Platform::create(['name' => 'Sito Web', 'slug' => 'website', 'is_active' => true]);
        \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $baseData = [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.com',
            'customer_phone' => '1234567890',
            'license_plate' => 'AB123CD',
        ];

        // Zero spots
        $response = $this->post(route('public.booking.store'), array_merge($baseData, ['spots' => 0]));
        $response->assertSessionHasErrors('spots');
        $this->assertDatabaseCount('reservations', 0);

        // Negative spots
        $response = $this->post(route('public.booking.store'), array_merge($baseData, ['spots' => -5]));
        $response->assertSessionHasErrors('spots');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_spots_exceeding_capacity_is_rejected()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 2, 'price' => 20, 'is_active' => true]);

        $platform = Platform::create(['name' => 'Sito Web', 'slug' => 'website', 'is_active' => true]);
        \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);
        $response = $this->post(route('public.booking.store'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 3, // Requires 3, capacity is 2
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.com',
            'customer_phone' => '1234567890',
            'license_plate' => 'AB123CD',
        ]);

        // Assicuriamoci che fallisca e che torni con un errore nella sessione
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_spots_rejected_when_capacity_reduced_by_allocations()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 10, 'price' => 20, 'is_active' => true]);

        // Alloco 6 posti per rentcar
        \App\Models\ParkingCapacityAllocation::create([
            'parking_id' => $parking->id,
            'allocation_type' => 'rentcar',
            'spots' => 6,
            'starts_at' => Carbon::today(),
            'ends_at' => Carbon::now()->addDays(5),
            'is_active' => true,
        ]);

        $platform = Platform::create(['name' => 'Sito Web', 'slug' => 'website', 'is_active' => true]);
        \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);
        // Provo a prenotare 5 posti (capacity 10 - 6 allocati = 4 disponibili)
        $response = $this->post(route('public.booking.store'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 5, // Requires 5, available is 4
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.com',
            'customer_phone' => '1234567890',
            'license_plate' => 'AB123CD',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_double_submit_fails_if_capacity_is_saturated_between_check_and_store()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 10, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 2, 'price' => 20, 'is_active' => true]);
        $platform = Platform::create(['name' => 'Sito Web', 'slug' => 'website', 'is_active' => true]);
        \App\Models\ParkingListing::create(['parking_id' => $parking->id, 'platform_id' => $platform->id, 'is_active' => true]);

        // 1. Cliente 1 fa il check per 2 posti -> esito positivo (ajax check non prenota)
        $responseCheck = $this->postJson(route('public.booking.check'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 2,
        ]);
        $responseCheck->assertJson(['available' => true]);

        // 2. Nel frattempo, Cliente 2 prenota 1 posto (consumando parte della capacità)
        \App\Models\Reservation::create([
            'parking_id' => $parking->id,
            'parking_listing_id' => \App\Models\ParkingListing::firstOrCreate(['parking_id' => $parking->id, 'platform_id' => Platform::first()->id])->id,
            'parking_product_id' => $product->id,
            'external_id' => 'RES-TEST',
            'customer_name' => 'Cliente 2',
            'customer_email' => 'c2@test.com',
            'customer_phone' => '123',
            'license_plate' => 'XX',
            'starts_at' => Carbon::tomorrow(),
            'ends_at' => Carbon::tomorrow()->addDays(2),
            'spots' => 1,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addMinutes(30),
            'price' => 60
        ]);

        // 3. Cliente 1 fa il submit del form. Il controller deve ri-verificare la capacità, che ora è 1 (product capacity 2 - 1 prenotato = 1 disponibile).
        // Il controller proverà ad assegnare ma il ParkingAssignmentService lancerà un'eccezione perché servono 2 posti.
        $responseStore = $this->post(route('public.booking.store'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 2,
            'customer_name' => 'Cliente 1',
            'customer_email' => 'c1@test.com',
            'customer_phone' => '123',
            'license_plate' => 'YY',
        ]);

        // Deve fallire con errore
        $responseStore->assertSessionHas('error');
        // La prenotazione di Cliente 1 non deve essere creata
        $this->assertDatabaseMissing('reservations', ['customer_name' => 'Cliente 1']);
    }

    public function test_fails_if_website_platform_is_missing()
    {
        $parking = Parking::create(['name' => 'Main Park', 'total_spots' => 100, 'is_active' => true]);
        $product = ParkingProduct::create(['parking_id' => $parking->id, 'name' => 'Standard', 'code' => 'std', 'capacity' => 10, 'price' => 20, 'is_active' => true]);

        $response = $this->post(route('public.booking.store'), [
            'product_code' => 'std',
            'arrival_date' => Carbon::tomorrow()->format('Y-m-d'),
            'arrival_time' => '10:00',
            'departure_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00',
            'passengers_count' => 1,
            'spots' => 2,
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.com',
            'customer_phone' => '1234567890',
            'license_plate' => 'AB123CD',
        ]);

        $response->assertStatus(404);
    }
}
