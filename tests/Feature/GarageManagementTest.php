<?php

use App\Enums\GarageBillingUnit;
use App\Enums\GaragePaymentMethod;
use App\Enums\GaragePaymentStatus;
use App\Models\GarageRate;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\ParkingSetting;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\GaragePaymentService;
use App\Services\GaragePricingService;
use App\Services\ParkingStayService;
use App\Services\ParkingSubscriptionService;
use Carbon\Carbon;

function garageScenario(int $capacity = 3): array
{
    $parking = Parking::query()->create([
        'name' => 'Garage Demo Test',
        'address' => 'Indirizzo dimostrativo',
        'total_spots' => $capacity,
        'capacity_mode' => 'per_product',
        'is_active' => true,
    ]);
    $product = ParkingProduct::query()->create([
        'parking_id' => $parking->id,
        'code' => 'garage_test',
        'name' => 'Auto scoperte demo',
        'capacity' => $capacity,
        'price' => 10,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $monthlyRate = GarageRate::query()->create([
        'parking_id' => $parking->id,
        'parking_product_id' => $product->id,
        'name' => 'Mensile test',
        'kind' => 'subscription',
        'billing_unit' => 'month',
        'price' => 90,
        'currency' => 'EUR',
        'grace_minutes' => 0,
        'minimum_units' => 1,
        'is_active' => true,
    ]);
    $dailyRate = GarageRate::query()->create([
        'parking_id' => $parking->id,
        'parking_product_id' => $product->id,
        'name' => 'Giornaliera test',
        'kind' => 'walk_in',
        'billing_unit' => 'twenty_four_hours',
        'price' => 15,
        'currency' => 'EUR',
        'grace_minutes' => 30,
        'minimum_units' => 1,
        'is_active' => true,
    ]);
    $operator = User::factory()->create(['role' => 'admin']);

    return compact('parking', 'product', 'monthlyRate', 'dailyRate', 'operator');
}

function createGarageSubscription(array $scenario, array $overrides = []): ParkingSubscription
{
    return app(ParkingSubscriptionService::class)->create([
        'parking_id' => $scenario['parking']->id,
        'parking_product_id' => $scenario['product']->id,
        'garage_rate_id' => $scenario['monthlyRate']->id,
        'customer_name' => 'Cliente Abbonato Test',
        'customer_email' => 'abbonato@example.test',
        'customer_phone' => '0000000000',
        'license_plate' => 'TEST-ABB-01',
        'starts_on' => '2030-01-01',
        'ends_on' => null,
        'reserved_spots' => 1,
        'payment_mode' => 'manual',
        'notes' => null,
        ...$overrides,
    ], $scenario['operator']);
}

test('garage pricing supports calendar days and started twenty-four-hour periods', function () {
    $scenario = garageScenario();
    $pricing = app(GaragePricingService::class);

    expect($pricing->quote(
        $scenario['dailyRate'],
        Carbon::parse('2030-01-01 08:00'),
        Carbon::parse('2030-01-02 08:20'),
    ))->toBe('15.00');

    $scenario['dailyRate']->update([
        'billing_unit' => GarageBillingUnit::CalendarDay->value,
        'price' => 12,
        'grace_minutes' => 0,
    ]);

    expect($pricing->quote(
        $scenario['dailyRate']->refresh(),
        Carbon::parse('2030-01-01 23:30'),
        Carbon::parse('2030-01-02 00:30'),
    ))->toBe('24.00');
});

test('an active subscription reserves inventory for its whole open-ended period', function () {
    $scenario = garageScenario(3);
    createGarageSubscription($scenario, ['reserved_spots' => 2]);

    $availability = app(AvailabilityService::class)->checkProductCapacity(
        $scenario['product'],
        Carbon::parse('2032-05-01 08:00'),
        Carbon::parse('2032-05-02 08:00'),
    );

    expect($availability->available)->toBeTrue()
        ->and($availability->availableSpots)->toBe(1);

    expect(fn () => createGarageSubscription($scenario, [
        'customer_name' => 'Secondo abbonato test',
        'license_plate' => 'TEST-ABB-02',
        'starts_on' => '2032-04-01',
        'reserved_spots' => 2,
    ]))->toThrow(LogicException::class, 'Gli abbonamenti attivi supererebbero');
});

test('a subscriber presence is not counted twice and cannot exceed reserved spots', function () {
    $scenario = garageScenario(1);
    $subscription = createGarageSubscription($scenario);
    $stayService = app(ParkingStayService::class);

    $stay = $stayService->checkIn([
        'parking_id' => $scenario['parking']->id,
        'parking_product_id' => null,
        'garage_rate_id' => null,
        'parking_subscription_id' => $subscription->id,
        'customer_name' => null,
        'customer_email' => null,
        'customer_phone' => null,
        'license_plate' => null,
        'starts_at' => '2030-02-01 08:00',
        'expected_ends_at' => '2030-02-01 18:00',
        'notes' => null,
    ], $scenario['operator']);

    expect($stay->estimated_total)->toBe('0.00')
        ->and(ParkingStay::query()->whereNull('parking_subscription_id')->count())->toBe(0);

    $availability = app(AvailabilityService::class)->checkProductCapacity(
        $scenario['product'],
        Carbon::parse('2030-02-01 09:00'),
        Carbon::parse('2030-02-01 17:00'),
    );

    expect($availability->available)->toBeFalse()
        ->and($availability->availableSpots)->toBe(0);

    expect(fn () => $stayService->checkIn([
        'parking_id' => $scenario['parking']->id,
        'parking_product_id' => null,
        'garage_rate_id' => null,
        'parking_subscription_id' => $subscription->id,
        'customer_name' => null,
        'customer_email' => null,
        'customer_phone' => null,
        'license_plate' => null,
        'starts_at' => '2030-02-01 10:00',
        'expected_ends_at' => '2030-02-01 16:00',
        'notes' => null,
    ], $scenario['operator']))->toThrow(LogicException::class, 'Tutti i posti riservati');
});

test('daily stay checkout payment and reversal preserve the complete history', function () {
    $scenario = garageScenario(2);
    $stay = app(ParkingStayService::class)->checkIn([
        'parking_id' => $scenario['parking']->id,
        'parking_product_id' => $scenario['product']->id,
        'garage_rate_id' => $scenario['dailyRate']->id,
        'parking_subscription_id' => null,
        'customer_name' => 'Cliente Giornaliero Test',
        'customer_email' => null,
        'customer_phone' => null,
        'license_plate' => 'TEST-DAY-01',
        'starts_at' => '2030-03-01 08:00',
        'expected_ends_at' => '2030-03-02 08:20',
        'notes' => null,
    ], $scenario['operator']);

    $stay = app(ParkingStayService::class)->checkOut(
        $stay,
        Carbon::parse('2030-03-02 08:20'),
        $scenario['operator'],
    );

    expect($stay->total_amount)->toBe('15.00');

    $payment = app(GaragePaymentService::class)->recordStayPayment($stay, [
        'method' => GaragePaymentMethod::Card->value,
        'amount' => 15,
        'paid_at' => '2030-03-02 08:25',
    ], $scenario['operator']);

    expect($stay->refresh()->payment_status)->toBe('paid')
        ->and($payment->recorded_by)->toBe($scenario['operator']->id);

    app(GaragePaymentService::class)->reverse($payment, 'Storno dimostrativo', $scenario['operator']);

    expect($stay->refresh()->payment_status)->toBe('unpaid')
        ->and($payment->refresh()->status)->toBe(GaragePaymentStatus::Reversed)
        ->and($payment->reversal_reason)->toBe('Storno dimostrativo')
        ->and($payment->reversed_by)->toBe($scenario['operator']->id);
});

test('demo Stripe checkout records a simulated subscription payment', function () {
    config()->set('demo.enabled', true);
    $scenario = garageScenario();
    $subscription = createGarageSubscription($scenario, ['payment_mode' => 'stripe']);

    $response = $this->actingAs($scenario['operator'])
        ->post(route('garage.subscriptions.stripe.checkout', $subscription));

    $payment = $subscription->payments()->latest()->firstOrFail();
    $response->assertRedirect(route('garage.stripe.simulation', $payment));

    $this->actingAs($scenario['operator'])
        ->post(route('garage.stripe.simulate', $payment), ['outcome' => 'success'])
        ->assertRedirect(route('garage.subscriptions.show', $subscription));

    expect($payment->refresh()->status)->toBe(GaragePaymentStatus::Paid)
        ->and($payment->raw_data['provider_data']['simulated'])->toBeTrue()
        ->and($subscription->refresh()->paid_through)->not->toBeNull();
});

test('garage operations are available to staff but rate settings remain admin only', function () {
    $scenario = garageScenario();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('garage.index'))->assertOk();
    $this->actingAs($staff)->get(route('garage.stays.index'))->assertOk();
    $this->actingAs($staff)->get(route('garage.rates.index'))->assertForbidden();
    $this->actingAs($scenario['operator'])->get(route('garage.rates.index'))->assertOk();
});

test('a garage stay can use the configured printable ticket', function () {
    $scenario = garageScenario();
    ParkingSetting::query()->create([
        'parking_id' => $scenario['parking']->id,
        'invoice_mode' => 'simulator',
        'ticket_format' => 'a6',
        'ticket_orientation' => 'portrait',
        'ticket_width_mm' => 80,
        'ticket_height_mm' => 60,
        'ticket_auto_open_on_exit' => false,
        'ticket_show_logo' => true,
        'ticket_title' => 'Ricevuta di ritiro veicolo',
    ]);
    $stay = ParkingStay::query()->create([
        'parking_id' => $scenario['parking']->id,
        'parking_product_id' => $scenario['product']->id,
        'garage_rate_id' => $scenario['dailyRate']->id,
        'reference' => 'SOSTA-TICKET-TEST',
        'customer_name' => 'Cliente Ticket Test',
        'license_plate' => 'TICKET-01',
        'starts_at' => '2030-04-01 08:00',
        'expected_ends_at' => '2030-04-01 18:00',
        'status' => 'active',
        'billing_unit' => 'twenty_four_hours',
        'unit_price' => 15,
        'estimated_total' => 15,
        'currency' => 'EUR',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($scenario['operator'])
        ->get(route('garage.stays.ticket', ['stay' => $stay, 'format' => 'label']))
        ->assertOk()
        ->assertSee('SOSTA-TICKET-TEST')
        ->assertSee('TICKET-01')
        ->assertSee('--paper-width: 80mm', false);
});
