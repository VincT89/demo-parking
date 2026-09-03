<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Services\ParkingAssignmentService;
use Carbon\Carbon;
use Exception;

class ParkingAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_first_available_parking()
    {
        // Parcheggio 1
        $parking1 = Parking::create(['name' => 'P1', 'total_spots' => 10, 'is_active' => true]);
        ParkingProduct::create(['parking_id' => $parking1->id, 'name' => 'Std', 'code' => 'std', 'capacity' => 2, 'price' => 10, 'is_active' => true]);

        // Parcheggio 2
        $parking2 = Parking::create(['name' => 'P2', 'total_spots' => 10, 'is_active' => true]);
        ParkingProduct::create(['parking_id' => $parking2->id, 'name' => 'Std', 'code' => 'std', 'capacity' => 5, 'price' => 10, 'is_active' => true]);

        $service = app(ParkingAssignmentService::class);

        $assignment = $service->findFirstAvailable('std', Carbon::tomorrow(), Carbon::tomorrow()->addDay(), 1);

        // Deve assegnare P1 perché è il primo nell'ordine (id minore)
        $this->assertEquals($parking1->id, $assignment['parking']->id);
        $this->assertEquals(20, $assignment['price']); // 2 days * 10 eur
    }

    public function test_skips_saturated_parking_and_assigns_second()
    {
        // Parcheggio 1 (Saturo - capacity 1, spots richiesti 2)
        $parking1 = Parking::create(['name' => 'P1', 'total_spots' => 10, 'is_active' => true]);
        ParkingProduct::create(['parking_id' => $parking1->id, 'name' => 'Std', 'code' => 'std', 'capacity' => 1, 'price' => 10, 'is_active' => true]);

        // Parcheggio 2 (Disponibile)
        $parking2 = Parking::create(['name' => 'P2', 'total_spots' => 10, 'is_active' => true]);
        ParkingProduct::create(['parking_id' => $parking2->id, 'name' => 'Std', 'code' => 'std', 'capacity' => 5, 'price' => 10, 'is_active' => true]);

        $service = app(ParkingAssignmentService::class);

        $assignment = $service->findFirstAvailable('std', Carbon::tomorrow(), Carbon::tomorrow()->addDay(), 2);

        // P1 non ha 2 posti, quindi deve passare a P2
        $this->assertEquals($parking2->id, $assignment['parking']->id);
    }

    public function test_throws_exception_if_no_parking_available()
    {
        // Parcheggio 1 (Saturo)
        $parking1 = Parking::create(['name' => 'P1', 'total_spots' => 10, 'is_active' => true]);
        ParkingProduct::create(['parking_id' => $parking1->id, 'name' => 'Std', 'code' => 'std', 'capacity' => 1, 'price' => 10, 'is_active' => true]);

        // Parcheggio 2 (Saturo)
        $parking2 = Parking::create(['name' => 'P2', 'total_spots' => 10, 'is_active' => true]);
        ParkingProduct::create(['parking_id' => $parking2->id, 'name' => 'Std', 'code' => 'std', 'capacity' => 1, 'price' => 10, 'is_active' => true]);

        $service = app(ParkingAssignmentService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Nessun parcheggio disponibile');

        $service->findFirstAvailable('std', Carbon::tomorrow(), Carbon::tomorrow()->addDay(), 2);
    }
}
