<?php

use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Models\ParkingListing;
use App\Models\Platform;

function createTestSetupForAvailability(int $capacity = 10, bool $isActive = true): array
{
    $parking = Parking::create([
        'name'        => 'Test Parking',
        'total_spots' => 100,
        'is_active'   => true,
    ]);

    $platform = Platform::create([
        'name'      => 'Test',
        'slug'      => 'test-' . uniqid(),
        'is_active' => true,
    ]);

    $listing = ParkingListing::create([
        'parking_id'  => $parking->id,
        'platform_id' => $platform->id,
        'is_active'   => true,
    ]);

    $product = ParkingProduct::create([
        'parking_id'  => $parking->id,
        'code'        => 'test_product',
        'name'        => 'Test Product',
        'capacity'    => $capacity,
        'price'       => 10.00,
        'sort_order'  => 1,
        'is_active'   => $isActive,
    ]);

    return [$parking, $listing, $product];
}

test('disponibile sotto capacità prodotto', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(10);
    $service = new AvailabilityService();

    $result = $service->checkProductCapacity(
        $product,
        Carbon::parse('2030-06-01 08:00:00'),
        Carbon::parse('2030-06-07 20:00:00')
    );

    expect($result->available)->toBeTrue();
    expect($result->availableSpots)->toBe(10);
});

test('non disponibile sopra capacità prodotto', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(1);
    $service = new AvailabilityService();

    Reservation::create([
        'parking_id' => $product->parking_id,
        'parking_listing_id' => $listing->id,
        'parking_product_id' => $product->id,
        'customer_name'      => 'Mario Rossi',
        'starts_at'          => '2030-06-01 08:00:00',
        'ends_at'            => '2030-06-07 20:00:00',
        'spots'              => 1,
        'status'             => 'confirmed',
    ]);

    $result = $service->checkProductCapacity(
        $product,
        Carbon::parse('2030-06-03 00:00:00'),
        Carbon::parse('2030-06-05 00:00:00')
    );

    expect($result->available)->toBeFalse();
    expect($result->availableSpots)->toBe(0);
});

test('esclude correttamente una reservation in update', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(1);
    $service = new AvailabilityService();

    $res = Reservation::create([
        'parking_id' => $product->parking_id,
        'parking_listing_id' => $listing->id,
        'parking_product_id' => $product->id,
        'customer_name'      => 'Mario Rossi',
        'starts_at'          => '2030-06-01 08:00:00',
        'ends_at'            => '2030-06-07 20:00:00',
        'spots'              => 1,
        'status'             => 'confirmed',
    ]);

    // Cerco di ri-testare l'occupazione ignorando quella prenotazione, utile per swap/update date
    $result = $service->checkProductCapacityExcluding(
        $product,
        Carbon::parse('2030-06-03 00:00:00'),
        Carbon::parse('2030-06-05 00:00:00'),
        1,
        $res->id
    );

    expect($result->available)->toBeTrue();
});

test('fallisce se si invoca la vecchia availability globale', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(10);
    $service = new AvailabilityService();

    expect(fn() => $service->checkParking(
        $product->parking,
        Carbon::parse('2030-06-01'),
        Carbon::parse('2030-06-07')
    ))->toThrow(\LogicException::class, 'Global parking availability is deprecated. Use product-based checks only.');
});

test('[Model Debt] un blocco a livello parcheggio riduce la disponibilita del singolo prodotto', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(10);
    
    // Add a block of 95 spots
    \App\Models\AvailabilityBlock::create([
        'parking_id' => $parking->id,
        'type' => \App\Enums\BlockType::Maintenance,
        'starts_at' => Carbon::parse('2030-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2030-06-06 00:00:00'),
        'spots' => 95,
        'reason' => 'Lavori',
    ]);

    $service = new AvailabilityService();
    $result = $service->checkProductCapacity(
        $product,
        Carbon::parse('2030-06-01 08:00:00'),
        Carbon::parse('2030-06-07 20:00:00')
    );

    expect($result->available)->toBeTrue();
    expect($result->availableSpots)->toBe(5); // 100 - 95 = 5 global. Min(10, 5) = 5
});

test('un blocco piu una prenotazione saturano correttamente il prodotto', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(5);
    
    // Add a block of 95 spots
    \App\Models\AvailabilityBlock::create([
        'parking_id' => $parking->id,
        'type' => \App\Enums\BlockType::Maintenance,
        'starts_at' => Carbon::parse('2030-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2030-06-06 00:00:00'),
        'spots' => 95,
        'reason' => 'Lavori',
    ]);

    // Add a reservation of 5 spots (for this product, which also consumes global)
    Reservation::create([
        'parking_id' => $parking->id,
        'parking_listing_id' => $listing->id,
        'parking_product_id' => $product->id,
        'customer_name' => 'Mario',
        'starts_at' => '2030-06-03 10:00:00',
        'ends_at' => '2030-06-05 10:00:00',
        'spots' => 5,
        'status' => 'confirmed',
    ]);

    $service = new AvailabilityService();
    $result = $service->checkProductCapacity(
        $product,
        Carbon::parse('2030-06-03 12:00:00'),
        Carbon::parse('2030-06-04 12:00:00')
    );

    expect($result->available)->toBeFalse();
    expect($result->availableSpots)->toBe(0); // 100 - 95 - 5 = 0
});

test('checkProductCapacityExcluding continua a escludere ma considera i blocchi', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(5);
    
    // Add a block of 95 spots
    \App\Models\AvailabilityBlock::create([
        'parking_id' => $parking->id,
        'type' => \App\Enums\BlockType::Maintenance,
        'starts_at' => Carbon::parse('2030-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2030-06-06 00:00:00'),
        'spots' => 95,
        'reason' => 'Lavori',
    ]);

    // Reservation we want to EXCLUDE (spots: 5)
    $res = Reservation::create([
        'parking_id' => $parking->id,
        'parking_listing_id' => $listing->id,
        'parking_product_id' => $product->id,
        'customer_name' => 'Mario',
        'starts_at' => '2030-06-03 10:00:00',
        'ends_at' => '2030-06-05 10:00:00',
        'spots' => 5,
        'status' => 'confirmed',
    ]);

    $service = new AvailabilityService();
    $result = $service->checkProductCapacityExcluding(
        $product,
        Carbon::parse('2030-06-03 12:00:00'),
        Carbon::parse('2030-06-04 12:00:00'),
        5, // asking for 5
        $res->id
    );

    expect($result->available)->toBeTrue();
    // Capacity 5 - 3 (blocks) = 2 available. We're asking for 2. The other res is excluded.
});

test('blocchi fuori periodo non impattano', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(10);
    
    // Add a block of 3 spots OUTSIDE the period
    \App\Models\AvailabilityBlock::create([
        'parking_id' => $parking->id,
        'type' => \App\Enums\BlockType::Maintenance,
        'starts_at' => Carbon::parse('2030-05-01 00:00:00'),
        'ends_at' => Carbon::parse('2030-05-15 00:00:00'),
        'spots' => 3,
        'reason' => 'Lavori passati',
        'is_active' => true,
    ]);

    $service = new AvailabilityService();
    $result = $service->checkProductCapacity(
        $product,
        Carbon::parse('2030-06-01 08:00:00'),
        Carbon::parse('2030-06-07 20:00:00')
    );

    expect($result->available)->toBeTrue();
    expect($result->availableSpots)->toBe(10); // unaffected
});

test('la capacita disponibile non scende mai sotto zero (evita output negativi)', function () {
    [$parking, $listing, $product] = createTestSetupForAvailability(5);
    
    // Block of 100 spots!
    \App\Models\AvailabilityBlock::create([
        'parking_id' => $parking->id,
        'type' => \App\Enums\BlockType::Maintenance,
        'starts_at' => Carbon::parse('2030-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2030-06-06 00:00:00'),
        'spots' => 100,
        'reason' => 'Mega blocco',
        'is_active' => true,
    ]);

    $service = new AvailabilityService();
    $result = $service->checkProductCapacity(
        $product,
        Carbon::parse('2030-06-03 12:00:00'),
        Carbon::parse('2030-06-04 12:00:00')
    );

    expect($result->available)->toBeFalse();
    expect($result->availableSpots)->toBe(0); // instead of -5
});