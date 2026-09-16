<?php

use App\Models\Customer;
use App\Models\GarageRate;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\User;
use App\Services\CustomerRegistryService;
use App\Services\CustomerHistoryService;
use App\Services\Invoicing\ElectronicInvoiceService;
use App\Services\ParkingStayService;
use App\Services\ParkingSubscriptionService;
use App\Services\ReservationService;
use Illuminate\Validation\ValidationException;

function registryData(array $changes = []): array
{
    return array_replace([
        'type' => 'person', 'name' => 'Cliente test anagrafica',
        'email' => 'CLIENTE@example.test', 'phone' => '+39 333 1234567',
        'country' => 'IT', 'vat_country' => 'IT',
        'license_plates' => "AA-123-BB\nCC 456 DD\naa123bb", 'notes' => 'Nota interna di prova',
    ], $changes);
}

function registryScenario(): array
{
    $scenario = invoicingScenario();
    $customer = app(CustomerRegistryService::class)->save(registryData());
    $operator = User::factory()->create(['role' => 'admin']);
    $monthlyRate = GarageRate::create([
        'parking_id' => $scenario['parking']->id, 'parking_product_id' => $scenario['product']->id,
        'name' => 'Mensile test', 'kind' => 'subscription', 'billing_unit' => 'month',
        'price' => 90, 'currency' => 'EUR', 'grace_minutes' => 0, 'minimum_units' => 1, 'is_active' => true,
    ]);
    $dailyRate = GarageRate::create([
        'parking_id' => $scenario['parking']->id, 'parking_product_id' => $scenario['product']->id,
        'name' => 'Giornaliera test', 'kind' => 'walk_in', 'billing_unit' => 'twenty_four_hours',
        'price' => 15, 'currency' => 'EUR', 'grace_minutes' => 0, 'minimum_units' => 1, 'is_active' => true,
    ]);
    return $scenario + compact('customer', 'operator', 'monthlyRate', 'dailyRate');
}

function registrySubscription(array $s, ?int $customerId = null): ParkingSubscription
{
    return app(ParkingSubscriptionService::class)->create([
        'customer_id' => $customerId,
        'parking_id' => $s['parking']->id, 'parking_product_id' => $s['product']->id,
        'garage_rate_id' => $s['monthlyRate']->id,
        'customer_name' => 'Nome originale contratto', 'customer_email' => 'originale@example.test',
        'customer_phone' => '3330000000', 'license_plate' => 'ABB123CD', 'starts_on' => '2030-01-01',
        'ends_on' => null, 'reserved_spots' => 2, 'payment_mode' => 'manual', 'notes' => null,
    ], $s['operator']);
}

function registryStay(array $s, ?ParkingSubscription $subscription = null, ?int $customerId = null): ParkingStay
{
    return app(ParkingStayService::class)->checkIn([
        'customer_id' => $customerId,
        'parking_id' => $s['parking']->id, 'parking_product_id' => $s['product']->id,
        'garage_rate_id' => $subscription ? null : $s['dailyRate']->id,
        'parking_subscription_id' => $subscription?->id,
        'customer_name' => null, 'customer_email' => null, 'customer_phone' => null,
        'license_plate' => 'DAY123CD', 'starts_at' => '2030-02-01 09:00',
        'expected_ends_at' => '2030-02-01 18:00', 'notes' => null,
    ], $s['operator']);
}

test('customer registry and lookup require authentication', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
    $this->getJson(route('customers.lookup', ['search' => 'Cliente']))->assertUnauthorized();
});

test('customer creation normalizes contacts and multiple plates and supports searching', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->post(route('customers.store'), registryData())->assertSessionHasNoErrors();
    $customer = Customer::firstOrFail();
    expect($customer->email)->toBe('cliente@example.test')
        ->and($customer->phone_normalized)->toBe('393331234567')
        ->and($customer->vehicles->pluck('license_plate')->all())->toBe(['AA123BB', 'CC456DD']);
    foreach (['Cliente', 'CLIENTE@example.test', '+39 333', 'AA-123-BB'] as $search) {
        $this->getJson(route('customers.lookup', ['search' => $search]))->assertOk()->assertJsonPath('0.id', $customer->id)
            ->assertJsonMissingPath('0.notes');
    }
    $this->get(route('customers.index', ['search' => 'AA123BB']))->assertOk()->assertSee($customer->name);
    $this->get(route('customers.edit', $customer))->assertOk();
});

test('matching contacts warn about duplicates without merging different customers', function () {
    $s = registryScenario();
    $this->actingAs($s['operator'])
        ->from(route('customers.create'))->post(route('customers.store'), registryData(['name' => 'Altro cliente']))
        ->assertSessionHasErrors('confirm_duplicate');
    expect(Customer::count())->toBe(1);
    $this->get(route('customers.create'))->assertOk()->assertSee('Possibili duplicati');
    $this->post(route('customers.store'), registryData(['name' => 'Altro cliente', 'confirm_duplicate' => 1]))
        ->assertSessionHasNoErrors();
    expect(Customer::count())->toBe(2)->and($s['customer']->fresh()->name)->toBe('Cliente test anagrafica');
});

test('linking a legacy reservation exposes its payments and invoices without rewriting snapshots', function () {
    $s = registryScenario();
    $originalName = $s['reservation']->customer_name;
    $payment = $s['reservation']->payments()->create([
        'provider' => 'onsite', 'status' => 'paid', 'amount' => 61, 'currency' => 'EUR', 'paid_at' => now(),
    ]);
    $invoice = app(ElectronicInvoiceService::class)->create($s['reservation'], invoiceCustomerData());
    $this->actingAs($s['operator'])->post(route('customers.records.link', $s['customer']), [
        'kind' => 'reservation', 'record_id' => $s['reservation']->id,
    ])->assertSessionHasNoErrors();
    $this->put(route('customers.update', $s['customer']), registryData(['name' => 'Nuovo nome anagrafica']))
        ->assertSessionHasNoErrors();
    expect($s['reservation']->fresh()->customer_name)->toBe($originalName)
        ->and($invoice->fresh()->customer_name)->toBe('Sample Customer');
    $rows = app(CustomerHistoryService::class)->query($s['customer'])->get();
    expect($rows->pluck('kind')->sort()->values()->all())->toBe(['invoice', 'payment', 'reservation']);
    $this->get(route('customers.show', $s['customer']))->assertOk()->assertSee($originalName)->assertSee($invoice->number);
    $this->get(route('customers.records', $s['customer']))->assertOk();
    $other = Customer::create(['name' => $originalName, 'type' => 'person']);
    expect(app(CustomerHistoryService::class)->query($other)->count())->toBe(0);
    $this->post(route('customers.records.link', $other), ['kind' => 'reservation', 'record_id' => $s['reservation']->id])
        ->assertSessionHasErrors('record');
    expect($s['reservation']->fresh()->customer_id)->toBe($s['customer']->id);
});

test('subscription linkage includes existing and future stays without accepting a different client', function () {
    $s = registryScenario();
    $subscription = registrySubscription($s);
    $existingStay = registryStay($s, $subscription);
    app(CustomerRegistryService::class)->link($s['customer'], 'subscription', $subscription->id);
    expect($existingStay->fresh()->customer_id)->toBe($s['customer']->id);
    $subscription->refresh();
    $futureStay = registryStay($s, $subscription);
    expect($futureStay->customer_id)->toBe($s['customer']->id);
    $other = Customer::create(['name' => 'Cliente distinto', 'type' => 'person']);
    expect(fn () => app(CustomerRegistryService::class)->link($other, 'stay', $existingStay->id))
        ->toThrow(ValidationException::class);
    expect($existingStay->fresh()->customer_id)->toBe($s['customer']->id);
});

test('manual reservation subscription and daily stay retain explicit customer selection', function () {
    $s = registryScenario();
    $this->actingAs($s['operator'])->post(route('reservations.store'), [
        'customer_id' => $s['customer']->id, 'parking_listing_id' => $s['listing']->id,
        'parking_product_id' => $s['product']->id, 'customer_name' => $s['customer']->name,
        'customer_email' => $s['customer']->email, 'customer_phone' => $s['customer']->phone,
        'license_plate' => 'AA123BB', 'starts_at' => '2030-03-01 09:00', 'ends_at' => '2030-03-02 09:00',
        'spots' => 1, 'passengers_count' => 1, 'price' => 20,
    ])->assertSessionHasNoErrors();
    expect($s['customer']->reservations()->count())->toBe(1);
    $subscription = registrySubscription($s, $s['customer']->id);
    $stay = registryStay($s, null, $s['customer']->id);
    expect($subscription->customer_id)->toBe($s['customer']->id)->and($stay->customer_id)->toBe($s['customer']->id);
    foreach (['reservations.create', 'garage.subscriptions.create', 'garage.stays.create'] as $route) {
        $this->get(route($route, ['customer_id' => $s['customer']->id]))->assertOk()->assertSee('data-customer-initial', false);
    }
});

test('history filters combine payment sources and paginate without leaking other clients', function () {
    $s = registryScenario();
    $s['reservation']->update(['customer_id' => $s['customer']->id]);
    $subscription = registrySubscription($s, $s['customer']->id);
    $subscription->payments()->create([
        'parking_id' => $s['parking']->id, 'provider' => 'manual', 'method' => 'cash',
        'status' => 'reversed', 'amount' => 90, 'currency' => 'EUR', 'paid_at' => '2030-02-01 12:00',
    ]);
    for ($i = 0; $i < 22; $i++) {
        $s['reservation']->payments()->create([
            'provider' => 'onsite', 'status' => 'paid', 'amount' => 1, 'currency' => 'EUR',
            'paid_at' => '2030-02-02 12:00',
        ]);
    }
    $response = $this->actingAs($s['operator'])->get(route('customers.show', [
        'customer' => $s['customer'], 'kind' => 'payment', 'date_from' => '2030-02-01', 'date_to' => '2030-02-02',
    ]))->assertOk();
    expect($response->viewData('activities')->total())->toBe(23);
    $page2 = $this->get(route('customers.show', ['customer' => $s['customer'], 'kind' => 'payment', 'page' => 2]))->assertOk();
    expect($page2->viewData('activities')->count())->toBe(3);
    $page2->assertSee('Stornato');
    $this->get(route('customers.show', ['customer' => $s['customer'], 'date_from' => '2031-01-01']))
        ->assertOk()->assertSee('Nessuna operazione collegata');
});

test('archiving is admin only preserves history and prevents new explicit links', function () {
    $s = registryScenario();
    app(CustomerRegistryService::class)->link($s['customer'], 'reservation', $s['reservation']->id);
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->patch(route('customers.status', $s['customer']), ['is_active' => false])->assertForbidden();
    $this->actingAs($s['operator'])->patch(route('customers.status', $s['customer']), ['is_active' => false])
        ->assertSessionHasNoErrors();
    $this->get(route('customers.show', $s['customer']))->assertOk()->assertSee($s['reservation']->external_id);
    $this->getJson(route('customers.lookup', ['search' => 'Cliente']))->assertExactJson([]);
    expect(fn () => registryStay($s, null, $s['customer']->id))->toThrow(ValidationException::class);
    expect($s['reservation']->fresh()->customer_id)->toBe($s['customer']->id);
    $this->patch(route('customers.status', $s['customer']), ['is_active' => true])->assertSessionHasNoErrors();
    $this->getJson(route('customers.lookup', ['search' => 'Cliente']))->assertJsonPath('0.id', $s['customer']->id);
});

test('history supports either date boundary and rejects reversed ranges with translated errors', function () {
    $s = registryScenario();
    $s['reservation']->update(['customer_id' => $s['customer']->id]);
    $this->actingAs($s['operator'])->get(route('customers.show', [
        'customer' => $s['customer'], 'date_to' => '2099-01-01',
    ]))->assertOk()->assertSee($s['reservation']->external_id);
    $this->get(route('customers.show', [
        'customer' => $s['customer'], 'date_from' => '2099-01-02', 'date_to' => '2099-01-01',
    ]))->assertSessionHasErrors('date_to');
    $this->withSession(['locale' => 'en_GB'])->post(route('customers.store'), registryData(['email' => 'invalid']))
        ->assertSessionHasErrors(['email' => 'The value of Email is invalid.']);
});

test('invoice drafts prefill the linked client fiscal data while existing invoices stay independent', function () {
    $s = registryScenario();
    $s['customer']->update([
        'type' => 'company', 'name' => 'Azienda dimostrativa', 'vat_number' => '12345678901',
        'address' => 'Indirizzo di test', 'city' => 'Roma', 'postal_code' => '00100',
    ]);
    $s['reservation']->update(['customer_id' => $s['customer']->id]);
    $this->actingAs($s['operator'])->get(route('invoices.create', $s['reservation']))
        ->assertOk()->assertSee('Azienda dimostrativa')->assertSee('12345678901')->assertSee('Indirizzo di test');
});

test('editing a subscription links earlier stays and keeps archived customer history intact', function () {
    $s = registryScenario();
    $subscription = registrySubscription($s);
    $stay = registryStay($s, $subscription);
    $data = [
        'customer_id' => $s['customer']->id, 'customer_name' => $subscription->customer_name,
        'license_plate' => $subscription->license_plate, 'starts_on' => '2030-01-01',
        'ends_on' => null, 'reserved_spots' => 2, 'status' => 'active',
    ];
    app(ParkingSubscriptionService::class)->update($subscription, $data);
    expect($stay->fresh()->customer_id)->toBe($s['customer']->id);
    $s['customer']->update(['is_active' => false]);
    app(ParkingSubscriptionService::class)->update($subscription, $data + ['notes' => 'Nota aggiornata']);
    expect($subscription->fresh()->notes)->toBe('Nota aggiornata');
    expect(fn () => app(ParkingSubscriptionService::class)->update($subscription, array_replace($data, ['customer_id' => null])))
        ->toThrow(ValidationException::class);
    expect($subscription->fresh()->customer_id)->toBe($s['customer']->id);
});

test('reservation updates preserve linked customers and reject reassignment', function () {
    $s = registryScenario();
    $s['reservation']->update(['customer_id' => $s['customer']->id]);
    $s['customer']->update(['is_active' => false]);
    $service = app(ReservationService::class);
    expect($service->update($s['reservation'], ['notes' => 'Nota aggiornata'])->success)->toBeTrue();
    expect($service->update($s['reservation']->fresh(), ['customer_id' => $s['customer']->id])->success)->toBeTrue();
    $other = Customer::create(['name' => 'Altro cliente dimostrativo']);
    expect($service->update($s['reservation']->fresh(), ['customer_id' => $other->id])->success)->toBeFalse();
    expect($s['reservation']->fresh()->customer_id)->toBe($s['customer']->id);
});
