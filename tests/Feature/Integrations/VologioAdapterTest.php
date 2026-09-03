<?php

use App\Integrations\Adapters\VologioAdapter;
use App\Integrations\Support\RooshProviderClient;
use App\Models\ParkingListing;
use App\Models\Platform;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.vologio.enabled', true);
    config()->set('services.vologio.base_url', 'https://api.vologio.test/v1');
    config()->set('services.vologio.api_key', 'test_key');
    config()->set('services.vologio.client_id', 'test_client');

    $this->platform = Platform::forceCreate([
        'name' => 'Vologio',
        'slug' => 'vologio',
        'is_active' => true,
    ]);

    $this->parking = \App\Models\Parking::forceCreate([
        'name' => 'Test',
        'capacity_mode' => 'shared',
        'is_active' => true,
        'total_spots' => 100,
    ]);
    
    $this->listing = ParkingListing::forceCreate([
        'platform_id' => $this->platform->id,
        'parking_id' => $this->parking->id,
        'external_id' => 'loc_123',
        'is_active' => true,
    ]);
});

it('fetches and normalizes reservations from vologio api', function () {
    Http::fake([
        'https://api.vologio.test/v1/bookings/findByModification*' => Http::response([
            'bookingsByModification' => [
                [
                    'id' => 'booking_1',
                    'service_location_id' => 'loc_123',
                    'service_id' => 'srv_999',
                    'customer' => [
                        'first_name' => 'Mario',
                        'last_name' => 'Rossi',
                        'email' => 'mario@example.com',
                    ],
                    'journey' => [
                        'departure_flight_number' => 'FR123',
                        'arrival_flight_number' => 'U2456',
                        'car' => ['license_plate' => 'AB123CD']
                    ],
                    'start' => '2026-06-01T10:00:00Z',
                    'end' => '2026-06-05T10:00:00Z',
                    'price' => ['amount' => 45.0, 'currency' => 'EUR'],
                    'status' => 'completed',
                    'remarks' => 'Test notes'
                ],
                [
                    'id' => 'booking_other',
                    'service_location_id' => 'loc_other',
                    'service_id' => 'srv_999',
                    'customer' => ['first_name' => 'Luigi', 'last_name' => 'Verdi'],
                    'start' => '2026-06-01T10:00:00Z',
                    'end' => '2026-06-05T10:00:00Z',
                    'status' => 'completed'
                ]
            ]
        ], 200)
    ]);

    $client = new RooshProviderClient();
    $adapter = new VologioAdapter($client);

    $from = Carbon::now()->subDay();
    $to = Carbon::now();

    $reservations = $adapter->fetchReservations($this->listing, $from, $to);

    // Deve tornare solo booking_1 perché booking_other ha loc_other
    expect($reservations)->toHaveCount(1);
    
    $normalized = $reservations[0];
    
    expect($normalized->external_id)->toBe('booking_1')
        ->and($normalized->external_product_ref)->toBe('srv_999')
        ->and($normalized->customer_name)->toBe('Mario Rossi')
        ->and($normalized->license_plate)->toBe('AB123CD')
        ->and($normalized->price)->toBe(45.0)
        ->and($normalized->currency)->toBe('EUR')
        ->and($normalized->status)->toBe('confirmed')
        ->and($normalized->flight_reference)->toBe('FR123 / U2456')
        ->and($normalized->notes)->toBe('Test notes');
});

it('throws exception if listing has no external_id', function () {
    $parking2 = \App\Models\Parking::forceCreate([
        'name' => 'Test 2',
        'capacity_mode' => 'shared',
        'is_active' => true,
        'total_spots' => 100,
    ]);

    $listingWithoutExternalId = ParkingListing::forceCreate([
        'platform_id' => $this->platform->id,
        'parking_id' => $parking2->id,
        'external_id' => null,
        'is_active' => true,
    ]);

    $client = new RooshProviderClient();
    $adapter = new VologioAdapter($client);

    $from = Carbon::now()->subDay();
    $to = Carbon::now();

    $adapter->fetchReservations($listingWithoutExternalId, $from, $to);
})->throws(\RuntimeException::class, 'non ha un external_id configurato');
