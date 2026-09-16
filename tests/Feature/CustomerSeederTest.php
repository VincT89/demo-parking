<?php

use App\Models\Customer;
use App\Models\GarageRate;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CustomerBackfillService;
use App\Services\CustomerHistoryService;
use App\Services\Invoicing\ElectronicInvoiceService;
use Database\Seeders\CustomerSeeder;

function backfillCopy(Reservation $reservation, array $changes = []): Reservation
{
    $copy = $reservation->replicate(['customer_id', 'external_id']);
    $copy->fill(['external_id' => 'BACKFILL-'.Illuminate\Support\Str::uuid(), ...$changes]);
    $copy->save();
    return $copy;
}

function backfillGarage(array $s): array
{
    $rate = GarageRate::create([
        'parking_id' => $s['parking']->id, 'parking_product_id' => $s['product']->id,
        'name' => 'Mensile dimostrativo', 'kind' => 'subscription', 'billing_unit' => 'month',
        'price' => 90, 'currency' => 'EUR', 'is_active' => true,
    ]);
    $subscription = ParkingSubscription::create([
        'parking_id' => $s['parking']->id, 'parking_product_id' => $s['product']->id,
        'garage_rate_id' => $rate->id, 'reference' => 'ABB-BACKFILL',
        'customer_name' => $s['reservation']->customer_name,
        'customer_email' => $s['reservation']->customer_email,
        'license_plate' => 'AA123BB', 'starts_on' => today()->startOfMonth(),
        'price' => 90, 'currency' => 'EUR', 'status' => 'active', 'reserved_spots' => 2, 'payment_mode' => 'manual',
    ]);
    $stay = ParkingStay::create([
        'parking_id' => $s['parking']->id, 'parking_product_id' => $s['product']->id,
        'parking_subscription_id' => $subscription->id, 'reference' => 'SOSTA-BACKFILL',
        'customer_name' => 'Conducente differente dal titolare', 'customer_email' => 'driver@example.test',
        'license_plate' => 'AA123BB', 'starts_at' => now()->subHour(),
        'expected_ends_at' => now()->addHours(3), 'status' => 'active', 'currency' => 'EUR',
    ]);
    return compact('subscription', 'stay');
}

test('customer seeder imports existing operations and exposes full history without changing snapshots', function () {
    $s = invoicingScenario();
    $g = backfillGarage($s);
    $second = backfillCopy($s['reservation'], ['customer_name' => ' sample CUSTOMER ', 'customer_email' => 'SAMPLE@EXAMPLE.TEST', 'license_plate' => 'BB-222-CC']);
    $daily = $g['stay']->replicate(['parking_subscription_id', 'reference', 'uuid']);
    $daily->fill(['reference' => 'GIORNALIERO-BACKFILL', 'customer_name' => 'Taylor Demo', 'customer_email' => null, 'license_plate' => 'DAY12MO']);
    $daily->save();
    $s['reservation']->payments()->create(['provider' => 'onsite', 'status' => 'paid', 'amount' => 61, 'currency' => 'EUR', 'paid_at' => now()]);
    $invoice = app(ElectronicInvoiceService::class)->create($s['reservation'], invoiceCustomerData());
    $g['subscription']->payments()->create(['parking_id' => $s['parking']->id, 'provider' => 'manual', 'method' => 'cash', 'status' => 'paid', 'amount' => 90, 'currency' => 'EUR', 'paid_at' => now()]);
    $before = $s['reservation']->refresh()->getRawOriginal();
    $invoiceBefore = $invoice->refresh()->getRawOriginal();

    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe(2)->and($report['linked'])->toBe(['subscription' => 1, 'reservation' => 2, 'stay' => 2])
        ->and($report['vehicles_added'])->toBe(4)->and($report['remaining'])->toBe(0);
    $customer = $s['reservation']->fresh()->customer;
    expect($second->fresh()->customer_id)->toBe($customer->id)
        ->and($g['subscription']->fresh()->customer_id)->toBe($customer->id)
        ->and($g['stay']->fresh()->customer_id)->toBe($customer->id)
        ->and($daily->fresh()->customer_id)->not->toBe($customer->id)
        ->and($s['reservation']->fresh()->getRawOriginal())->toBe(array_replace($before, ['customer_id' => $customer->id]))
        ->and($invoice->fresh()->getRawOriginal())->toBe($invoiceBefore);
    expect(app(CustomerHistoryService::class)->query($customer)->count())->toBe(7);

    $repeat = app(CustomerBackfillService::class)->run();
    expect($repeat['created'])->toBe(0)->and(array_sum($repeat['linked']))->toBe(0)->and($repeat['vehicles_added'])->toBe(0);
    expect(Customer::count())->toBe(2);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('customers.index'))->assertOk()->assertSee('Sample Customer')->assertSee('Taylor Demo');
    $this->get(route('customers.show', $customer))->assertOk()->assertSee($invoice->number)->assertSee('ABB-BACKFILL');
});

test('same names or shared contacts do not merge different customers', function () {
    $s = invoicingScenario();
    $second = backfillCopy($s['reservation'], ['customer_email' => 'another@example.test', 'license_plate' => 'OTHER22']);
    $third = backfillCopy($s['reservation'], ['customer_name' => 'Persona differente', 'license_plate' => 'OTHER33']);
    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe(3)->and($report['remaining'])->toBe(0);
    expect(collect([$s['reservation']->fresh()->customer_id, $second->fresh()->customer_id, $third->fresh()->customer_id])->unique()->count())->toBe(3);
});

test('seeder handles missing email without grouping by name alone', function ($phone, $plate, $expected) {
    $s = invoicingScenario();
    $s['reservation']->update(['customer_email' => null, 'customer_phone' => $phone, 'license_plate' => $plate]);
    $second = backfillCopy($s['reservation']);
    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe($expected)->and($report['remaining'])->toBe(0);
    $this->seed(CustomerSeeder::class);
    expect(Customer::count())->toBe($expected);
})->with([
    'same name and phone' => ['+39 333 1234567', null, 1],
    'same name and plate' => [null, 'ABC123', 1],
    'name only' => [null, null, 2],
    'placeholder phone' => ['0000000000', null, 2],
]);

test('ambiguous existing profiles are reported rather than merged or duplicated', function () {
    $s = invoicingScenario();
    foreach ([1, 2] as $number) {
        Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test', 'notes' => 'Scheda '.$number]);
    }
    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe(0)->and($report['remaining'])->toBe(1)->and($report['issues'][0]['reason'])->toBe('ambiguous');
    expect($s['reservation']->fresh()->customer_id)->toBeNull()->and(Customer::count())->toBe(2);
});

test('conflicting email and phone matches are left for manual review', function () {
    $s = invoicingScenario();
    Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test']);
    Customer::create(['name' => 'Sample Customer', 'email' => 'other@example.test', 'phone' => '+39 333 1234567']);
    $s['reservation']->update(['customer_phone' => '+39 333 1234567']);
    $report = app(CustomerBackfillService::class)->run();
    expect($report['issues'][0]['reason'])->toBe('ambiguous')->and($s['reservation']->fresh()->customer_id)->toBeNull();
});

test('existing historical links recognize edited profiles and never overwrite profile data', function () {
    $s = invoicingScenario();
    $customer = Customer::create(['name' => 'Nome aggiornato', 'email' => 'new@example.test', 'notes' => 'Conservare nota', 'vat_number' => '12345678901']);
    $s['reservation']->update(['customer_id' => $customer->id]);
    $second = backfillCopy($s['reservation'], ['license_plate' => 'HIST123']);
    $before = $customer->refresh()->getRawOriginal();
    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe(0)->and($second->fresh()->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->getRawOriginal())->toBe($before);
});

test('archived profiles stay archived and existing subscription ownership is respected', function () {
    $s = invoicingScenario();
    $customer = Customer::create(['name' => 'Sample Customer', 'email' => 'sample@example.test', 'is_active' => false]);
    $g = backfillGarage($s);
    $g['subscription']->update(['customer_id' => $customer->id]);
    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe(0)->and($report['remaining'])->toBe(1)
        ->and($report['issues'][0]['reason'])->toBe('archived')
        ->and($s['reservation']->fresh()->customer_id)->toBeNull()
        ->and($g['stay']->fresh()->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->is_active)->toBeFalse();
});

test('seeder preserves conflicting manual stay links without guessing the subscription owner', function () {
    $s = invoicingScenario();
    $g = backfillGarage($s);
    $other = Customer::create(['name' => 'Collegamento manuale']);
    $g['stay']->update(['customer_id' => $other->id]);
    $report = app(CustomerBackfillService::class)->run();
    expect($g['subscription']->fresh()->customer_id)->toBeNull()
        ->and($g['stay']->fresh()->customer_id)->toBe($other->id)
        ->and($report['issues'][0]['reason'])->toBe('conflicting_links');
});

test('invalid records are skipped without creating placeholder customers', function () {
    $s = invoicingScenario();
    $s['reservation']->update(['customer_name' => '']);
    $invalid = backfillCopy($s['reservation'], ['customer_name' => 'Nome presente', 'customer_email' => 'invalid-address']);
    $report = app(CustomerBackfillService::class)->run();
    expect($report['created'])->toBe(0)->and($report['remaining'])->toBe(2)
        ->and(array_column($report['issues'], 'reason'))->toBe(['missing_name', 'invalid_data']);
});

test('dedicated customer seeder can be called without invoking the destructive general seeder', function () {
    $s = invoicingScenario();
    $this->artisan('db:seed', ['--class' => 'CustomerSeeder', '--force' => true])->assertSuccessful();
    expect(Reservation::count())->toBe(1)->and($s['reservation']->fresh()->customer_id)->not->toBeNull();
    $this->artisan('db:seed', ['--class' => CustomerSeeder::class, '--force' => true])->assertSuccessful();
    expect(Customer::count())->toBe(1)->and(Reservation::count())->toBe(1);
});
