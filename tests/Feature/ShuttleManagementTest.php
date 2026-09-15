<?php

use App\Enums\ShuttleDirection;
use App\Enums\ShuttleTripStatus;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\Reservation;
use App\Models\ShuttleSetting;
use App\Models\ShuttleTrip;
use App\Models\ShuttleVehicle;
use App\Models\User;
use App\Services\ShuttlePlannerService;
use Carbon\Carbon;

function shuttleScenario(): array
{
    $parking = Parking::query()->create([
        'name' => 'Parcheggio Navetta Test',
        'address' => 'Indirizzo dimostrativo',
        'total_spots' => 50,
        'capacity_mode' => 'per_product',
        'is_active' => true,
    ]);
    $product = ParkingProduct::query()->create([
        'parking_id' => $parking->id,
        'code' => 'shuttle_test',
        'name' => 'Auto navetta test',
        'capacity' => 50,
        'price' => 25,
        'is_active' => true,
    ]);
    $platform = Platform::query()->create([
        'name' => 'Sito test',
        'slug' => 'shuttle-test',
        'is_active' => true,
    ]);
    $listing = ParkingListing::query()->create([
        'parking_id' => $parking->id,
        'platform_id' => $platform->id,
        'name' => 'Canale test',
        'is_active' => true,
    ]);
    $settings = ShuttleSetting::query()->create([
        'parking_id' => $parking->id,
        'is_enabled' => true,
        'default_capacity' => 4,
        'grouping_window_minutes' => 30,
        'turnaround_minutes' => 45,
        'outbound_offset_minutes' => 15,
        'return_offset_minutes' => 0,
    ]);
    $vehicle = ShuttleVehicle::query()->create([
        'parking_id' => $parking->id,
        'name' => 'Navetta Test',
        'license_plate' => 'NAV-TEST',
        'seats' => 4,
        'is_active' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    return compact('parking', 'product', 'platform', 'listing', 'settings', 'vehicle', 'admin');
}

function shuttleReservation(array $scenario, string $externalId, string $start, string $end, ?int $passengers): Reservation
{
    return Reservation::query()->create([
        'parking_id' => $scenario['parking']->id,
        'parking_listing_id' => $scenario['listing']->id,
        'parking_product_id' => $scenario['product']->id,
        'external_id' => $externalId,
        'customer_name' => 'Cliente '.$externalId,
        'customer_email' => strtolower($externalId).'@example.test',
        'license_plate' => $externalId,
        'flight_reference' => 'DEMO-'.substr($externalId, -1),
        'starts_at' => $start,
        'ends_at' => $end,
        'spots' => 1,
        'passengers_count' => $passengers,
        'status' => 'confirmed',
        'price' => 25,
    ]);
}

test('planner splits a large group across trips and regeneration is idempotent', function () {
    $scenario = shuttleScenario();
    shuttleReservation($scenario, 'SHUTTLE-A', '2030-06-10 08:00', '2030-06-12 18:00', 3);
    shuttleReservation($scenario, 'SHUTTLE-B', '2030-06-10 08:10', '2030-06-12 18:20', 3);

    $planner = app(ShuttlePlannerService::class);
    $created = $planner->generate($scenario['parking'], Carbon::parse('2030-06-10'));

    $outboundTrips = ShuttleTrip::query()
        ->where('direction', ShuttleDirection::Outbound->value)
        ->with('assignments')
        ->get();

    expect($created)->toBe(2)
        ->and($outboundTrips)->toHaveCount(2)
        ->and($outboundTrips->sum(fn (ShuttleTrip $trip) => $trip->assignments->sum('passengers')))->toBe(6)
        ->and($outboundTrips->max(fn (ShuttleTrip $trip) => $trip->assignments->sum('passengers')))->toBeLessThanOrEqual(4)
        ->and($planner->generate($scenario['parking'], Carbon::parse('2030-06-10')))->toBe(0);
});

test('planner keeps manual trip edits and treats legacy passenger count as one', function () {
    $scenario = shuttleScenario();
    shuttleReservation($scenario, 'SHUTTLE-LEGACY', '2030-07-01 09:00', '2030-07-02 12:00', null);
    $manualTrip = ShuttleTrip::query()->create([
        'parking_id' => $scenario['parking']->id,
        'shuttle_vehicle_id' => $scenario['vehicle']->id,
        'direction' => 'outbound',
        'scheduled_at' => '2030-07-01 09:22',
        'status' => 'confirmed',
        'capacity' => 4,
        'is_generated' => false,
        'notes' => 'Orario scelto dal gestore',
    ]);

    expect(app(ShuttlePlannerService::class)->generate(
        $scenario['parking'],
        Carbon::parse('2030-07-01'),
    ))->toBe(0);

    $manualTrip->refresh()->load('assignments');
    expect($manualTrip->scheduled_at->format('H:i'))->toBe('09:22')
        ->and($manualTrip->status)->toBe(ShuttleTripStatus::Confirmed)
        ->and($manualTrip->is_generated)->toBeFalse()
        ->and($manualTrip->assignments->sum('passengers'))->toBe(1);
});

test('cancelled or wrong-day trips reject manual group assignment', function () {
    $scenario = shuttleScenario();
    $reservation = shuttleReservation($scenario, 'SHUTTLE-C', '2030-08-01 09:00', '2030-08-02 12:00', 2);
    $trip = ShuttleTrip::query()->create([
        'parking_id' => $scenario['parking']->id,
        'direction' => 'outbound',
        'scheduled_at' => '2030-08-01 09:15',
        'status' => 'cancelled',
        'capacity' => 4,
        'is_generated' => false,
    ]);

    expect(fn () => app(ShuttlePlannerService::class)->assign($trip, $reservation, 1))
        ->toThrow(LogicException::class, 'Il viaggio non accetta più modifiche');

    $trip->update([
        'status' => 'proposed',
        'scheduled_at' => '2030-08-03 09:15',
    ]);

    expect(fn () => app(ShuttlePlannerService::class)->assign($trip->refresh(), $reservation, 1))
        ->toThrow(LogicException::class, 'La prenotazione non appartiene alla giornata');
});

test('shuttle plan is operational for staff while configuration remains admin only', function () {
    $scenario = shuttleScenario();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('shuttles.index', ['parking_id' => $scenario['parking']->id, 'date' => '2030-09-01']))
        ->assertOk();
    $this->actingAs($staff)->get(route('shuttles.settings.edit'))->assertForbidden();
    $this->actingAs($scenario['admin'])->get(route('shuttles.settings.edit'))->assertOk();
});

test('disabled shuttle planning does not create trips', function () {
    $scenario = shuttleScenario();
    $scenario['settings']->update(['is_enabled' => false]);

    expect(fn () => app(ShuttlePlannerService::class)->generate(
        $scenario['parking'],
        Carbon::parse('2030-10-01'),
    ))->toThrow(LogicException::class, 'La gestione navetta non è attiva');

    expect(ShuttleTrip::query()->count())->toBe(0);
});
