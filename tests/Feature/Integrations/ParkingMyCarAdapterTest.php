<?php

namespace Tests\Feature\Integrations;

use App\Integrations\Adapters\ParkingMyCarAdapter;
use App\Integrations\DTO\NormalizedReservation;
use App\Integrations\Support\ParkingMyCarClient;
use App\Models\ParkingListing;
use App\Models\Platform;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ParkingMyCarAdapterTest extends TestCase
{
    use RefreshDatabase;

    private ParkingMyCarAdapter $adapter;
    private $clientMock;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.parking_my_car.default_product_ref', 'DEFAULT');

        $this->clientMock = $this->createMock(ParkingMyCarClient::class);
        $this->adapter = new ParkingMyCarAdapter($this->clientMock);
    }

    public function test_errors_if_listing_has_no_external_id()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => null]);
        $listing->setRelation('platform', $platform);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("ParkingListing ID {$listing->id} non ha external_id configurato.");

        $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());
    }

    public function test_filters_by_parking_listing_external_id_and_normalizes_confirmed()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_1',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'mario@example.com'],
                'vehicle' => ['license_plate' => 'AB123CD'],
                'price' => 50.00,
                'status' => 'confirmed',
                'product_id' => 'PROD_1',
                'spots' => 1,
                'created' => 1624505000,
                'updated' => 1624505000,
            ],
            [
                'id' => 'BOOK_2',
                'parking_id' => '999', // should be filtered out
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());

        $this->assertCount(1, $reservations);
        /** @var NormalizedReservation $res */
        $res = $reservations[0];

        $this->assertEquals('BOOK_1', $res->external_id);
        $this->assertEquals('PROD_1', $res->external_product_ref);
        $this->assertEquals('Mario Rossi', $res->customer_name);
        $this->assertEquals('mario@example.com', $res->customer_email);
        $this->assertEquals('AB123CD', $res->license_plate);
        $this->assertEquals(50.00, $res->price);
        $this->assertEquals('confirmed', $res->status);
        $this->assertNotNull($res->platform_created_at);
        $this->assertNotNull($res->platform_updated_at);
        $this->assertEquals(
            Carbon::createFromTimestamp(1624505000, 'Europe/Rome')->toDateTimeString(),
            $res->platform_created_at->toDateTimeString()
        );
    }

    public function test_normalizes_cancelled_reservation()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_3',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Luigi', 'last_name' => 'Verdi'],
                'status' => 'annullata', // should map to cancelled
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());

        $this->assertCount(1, $reservations);
        $this->assertEquals('cancelled', $reservations[0]->status);
    }

    public function test_fallback_DEFAULT_if_no_product_sent()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_4',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Anna', 'last_name' => 'Neri'],
                'status' => 'confirmed',
                // product_id missing
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());

        $this->assertCount(1, $reservations);
        $this->assertEquals('DEFAULT', $reservations[0]->external_product_ref);
    }

    public function test_parking_my_car_maps_cancellato_to_cancelled()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_5',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Gino', 'last_name' => 'Gini'],
                'status' => 'cancellato',
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());
        $this->assertEquals('cancelled', $reservations[0]->status);
    }

    public function test_parking_my_car_maps_cancellata_to_cancelled()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_6',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Gina', 'last_name' => 'Gini'],
                'status' => 'cancellata',
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());
        $this->assertEquals('cancelled', $reservations[0]->status);
    }

    public function test_parking_my_car_cancelled_false_does_not_force_cancelled_status()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_7',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Pino', 'last_name' => 'Pini'],
                'status' => 'confermata',
                'cancelled' => false,
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());
        $this->assertEquals('confirmed', $reservations[0]->status);
        $this->assertNull($reservations[0]->platform_cancelled_at);
    }

    public function test_parking_my_car_cancelled_zero_does_not_force_cancelled_status()
    {
        $platform = new Platform(['id' => 1, 'slug' => 'parking-my-car']);
        $listing = new ParkingListing(['id' => 1, 'platform_id' => $platform->id, 'external_id' => '123']);
        $listing->setRelation('platform', $platform);

        $this->clientMock->method('findBookingsByModification')->willReturn([
            [
                'id' => 'BOOK_8',
                'parking_id' => '123',
                'start_dtm' => '2026-06-10 10:00:00',
                'end_dtm' => '2026-06-15 10:00:00',
                'customer' => ['first_name' => 'Pino', 'last_name' => 'Pini'],
                'status' => 'confermata',
                'cancelled' => 0,
            ]
        ]);

        $reservations = $this->adapter->fetchReservations($listing, Carbon::now(), Carbon::now());
        $this->assertEquals('confirmed', $reservations[0]->status);
        $this->assertNull($reservations[0]->platform_cancelled_at);
    }
}
