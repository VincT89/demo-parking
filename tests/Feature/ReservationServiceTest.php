<?php

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use App\Enums\ReservationStatus;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createTestSetupForReservation(int $productCapacity = 10, bool $productActive = true): array
{
    $parking = Parking::create([
        'name'        => 'Test Parking',
        'total_spots' => 100,
        'is_active'   => true,
    ]);

    $platform = Platform::create([
        'name'      => 'Test Platform',
        'slug'      => 'test-platform-' . uniqid(),
        'is_active' => true,
    ]);

    $listing = ParkingListing::create([
        'parking_id'     => $parking->id,
        'platform_id'    => $platform->id,
        'is_active'      => true,
    ]);

    $product = ParkingProduct::create([
        'parking_id'  => $parking->id,
        'code'        => 'test_product',
        'name'        => 'Test Product',
        'capacity'    => $productCapacity,
        'price'       => 10.00,
        'sort_order'  => 1,
        'is_active'   => $productActive,
    ]);

    return [$parking, $listing, $product];
}

function makeService(): ReservationService
{
    return new ReservationService(new AvailabilityService());
}

test('crea reservation con prodotto valido', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    $result = $service->create($listing, [
        'parking_product_id' => $product->id,
        'customer_name' => 'Mario Rossi',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots'     => 1,
    ]);

    expect($result->success)->toBeTrue();
    expect($result->reservation->parking_product_id)->toBe($product->id);
});

test('rifiuta prodotto inesistente', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    $result = $service->create($listing, [
        'parking_product_id' => 9999, // inesistente
        'customer_name' => 'Mario Rossi',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
    ]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('non esiste');
});

test('rifiuta prodotto di parking diverso dal listing', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    
    $otherParking = Parking::create(['name' => 'Other', 'total_spots' => 100, 'is_active' => true]);
    $otherProduct = ParkingProduct::create([
        'parking_id' => $otherParking->id,
        'code' => 'other',
        'name' => 'Other',
        'capacity' => 10,
        'price' => 10,
        'is_active' => true,
    ]);

    $service = makeService();

    $result = $service->create($listing, [
        'parking_product_id' => $otherProduct->id,
        'customer_name' => 'Mario Rossi',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
    ]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('NON appartiene al parcheggio del listing');
});

test('rifiuta prodotto inattivo', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10, false);
    $service = makeService();

    $result = $service->create($listing, [
        'parking_product_id' => $product->id,
        'customer_name' => 'Mario Rossi',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
    ]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('disattivato');
});

test('rifiuta over-capacity sulla categoria', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(1);
    $service = makeService();

    // Occupa l'unico posto
    $service->create($listing, [
        'parking_product_id' => $product->id,
        'customer_name' => 'User 1',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots' => 1,
    ]);

    // Tentativo in overbooking
    $result = $service->create($listing, [
        'parking_product_id' => $product->id,
        'customer_name' => 'User 2',
        'starts_at' => '2030-06-02 08:00:00',
        'ends_at'   => '2030-06-05 20:00:00',
        'spots' => 1,
    ]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Posti insufficienti');
});

test('update non conta due volte la reservation stessa', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(1);
    $service = makeService();

    $created = $service->create($listing, [
        'parking_product_id' => $product->id,
        'customer_name' => 'Mario Rossi',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots' => 1,
    ]);

    $result = $service->update($created->reservation, [
        'customer_name' => 'Mario Rossi Modificato',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots' => 1,
    ]);

    expect($result->success)->toBeTrue();
    expect($result->reservation->customer_name)->toBe('Mario Rossi Modificato');
});

test('importFromExternal crea una nuova prenotazione se external_id non esiste', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    $result = $service->importFromExternal($listing, [
        'external_id' => 'EXT-123',
        'parking_product_id' => $product->id,
        'customer_name' => 'Imported User',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots' => 1,
    ]);

    expect($result->isSuccess())->toBeTrue();
    expect($result->action)->toBe(\App\Services\Results\ImportAction::Created);
    expect($result->reservation->external_id)->toBe('EXT-123');
    expect($result->reservation->customer_name)->toBe('Imported User');
});

test('importFromExternal aggiorna una prenotazione esistente se external_id corrisponde', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    // Create directly
    Reservation::create([
        'parking_id' => $parking->id,
        'parking_listing_id' => $listing->id,
        'parking_product_id' => $product->id,
        'external_id' => 'EXT-123',
        'customer_name' => 'Original User',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots' => 1,
        'status' => 'confirmed'
    ]);

    // Import again with same external_id
    $result = $service->importFromExternal($listing, [
        'external_id' => 'EXT-123',
        'parking_product_id' => $product->id,
        'customer_name' => 'Updated User',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
        'spots' => 1,
    ]);

    expect($result->isSuccess())->toBeTrue();
    expect($result->action)->toBe(\App\Services\Results\ImportAction::Updated);
    expect($result->reservation->customer_name)->toBe('Updated User');
});

test('importFromExternal fallisce se manca external_id', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    $result = $service->importFromExternal($listing, [
        // 'external_id' => '...', missing!
        'parking_product_id' => $product->id,
        'customer_name' => 'Imported User',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
    ]);

    expect($result->isSuccess())->toBeFalse();
    expect($result->action)->toBe(\App\Services\Results\ImportAction::Failed);
    expect($result->error)->toContain('obbligatorio');
});

test('importFromExternal fallisce se manca parking_product_id', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    $result = $service->importFromExternal($listing, [
        'external_id' => 'EXT-123',
        // 'parking_product_id' => $product->id, missing!
        'customer_name' => 'Imported User',
        'starts_at' => '2030-06-01 08:00:00',
        'ends_at'   => '2030-06-07 20:00:00',
    ]);

    expect($result->isSuccess())->toBeFalse();
    expect($result->action)->toBe(\App\Services\Results\ImportAction::Failed);
    expect($result->error)->toContain('non risolto');
});

test('update non sovrascrive timestamp esistente con null', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    $existing = \App\Models\Reservation::create([
        'parking_id' => $parking->id,
        'parking_product_id' => $product->id,
        'parking_listing_id' => $listing->id,
        'customer_name' => 'Mario',
        'customer_email' => 'mario@example.com',
        'starts_at' => \Carbon\Carbon::tomorrow(),
        'ends_at' => \Carbon\Carbon::tomorrow()->addDays(2),
        'status' => \App\Enums\ReservationStatus::Confirmed->value,
        'spots' => 1,
        'price' => 10,
        'external_id' => 'EXT_TEST_99',
        'platform_created_at' => \Carbon\Carbon::parse('2026-01-01 10:00:00'),
    ]);

    $result = $service->update($existing, [
        'platform_created_at' => null, // Should not overwrite
        'customer_name' => 'Luigi', // Should update
    ]);

    expect($result->reservation->customer_name)->toBe('Luigi');
    expect($result->reservation->platform_created_at->format('Y-m-d H:i:s'))->toBe('2026-01-01 10:00:00');
});

test('importFromExternal su reservation gia cancellata e idempotente', function () {
    [$parking, $listing, $product] = createTestSetupForReservation(10);
    $service = makeService();

    // Create a confirmed reservation first
    $existing = \App\Models\Reservation::create([
        'parking_id' => $parking->id,
        'parking_product_id' => $product->id,
        'parking_listing_id' => $listing->id,
        'customer_name' => 'Mario',
        'customer_email' => 'mario@example.com',
        'starts_at' => \Carbon\Carbon::tomorrow(),
        'ends_at' => \Carbon\Carbon::tomorrow()->addDays(2),
        'status' => \App\Enums\ReservationStatus::Confirmed->value,
        'spots' => 1,
        'price' => 10,
        'external_id' => 'EXT_CANCEL_TEST',
    ]);

    // First import: cancels it
    $result1 = $service->importFromExternal($listing, [
        'external_id' => 'EXT_CANCEL_TEST',
        'status' => \App\Enums\ReservationStatus::Cancelled->value,
        'platform_cancelled_at' => \Carbon\Carbon::parse('2026-02-01 10:00:00')->format('Y-m-d H:i:s'),
    ]);
    expect($result1->isSuccess())->toBeTrue();
    expect($result1->action)->toBe(\App\Services\Results\ImportAction::Updated);
    $existing->refresh();
    expect($existing->status)->toBe(\App\Enums\ReservationStatus::Cancelled);
    expect($existing->platform_cancelled_at->format('Y-m-d H:i:s'))->toBe('2026-02-01 10:00:00');

    // Second import: already cancelled
    $result2 = $service->importFromExternal($listing, [
        'external_id' => 'EXT_CANCEL_TEST',
        'status' => \App\Enums\ReservationStatus::Cancelled->value,
        'platform_cancelled_at' => null,
    ]);
    expect($result2->isSuccess())->toBeTrue();
    expect($result2->action)->toBe(\App\Services\Results\ImportAction::Updated); // Should be updated, not failed
    $existing->refresh();
    // platform_cancelled_at should not be overwritten by null
    expect($existing->platform_cancelled_at->format('Y-m-d H:i:s'))->toBe('2026-02-01 10:00:00');
});