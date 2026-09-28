<?php

namespace Tests\Feature;

use App\Models\{Customer, GaragePayment, Parking, ParkingSubscription, Reservation, ShuttleTripAssignment};
use App\Services\{CustomerBackfillService, CustomerHistoryService};
use App\Tenancy\ParkingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DemoManagementFixtures;
use Tests\TestCase;

class DemoManagementIsolationTest extends TestCase
{
    use RefreshDatabase;
    protected bool $legacyFixtures = false;
    private array $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->scenario = DemoManagementFixtures::create();
    }

    private function partner(): array
    {
        $b = $this->scenario['parks']['B'];
        $this->actingAs($this->scenario['partner'])->withSession(['active_parking_id' => $b['parking']->id]);
        $this->withHeader('X-Parking-Context', (string) $b['parking']->id);
        return $b;
    }

    public function test_partner_lists_only_own_customers_garage_shuttles_and_invoices(): void
    {
        $b = $this->partner();
        $this->getJson(route('customers.lookup', ['search' => 'shared']))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $b['customer']->id);
        foreach ([['customers.index', 'Cliente privato'], ['garage.subscriptions.index', 'PRIVATE-SUB-'], ['garage.stays.index', 'PRIVATE-STAY-'], ['garage.rates.index', 'Tariffa privata'], ['shuttles.index', 'Navetta privata'], ['invoices.index', 'PRIVATE-INVOICE-']] as [$route, $label]) {
            $separator = str_ends_with($label, '-') ? '' : ' ';
            $this->get(route($route))->assertOk()->assertSee($label.$separator.'B')->assertDontSee($label.$separator.'A');
        }
        $this->get(route('customers.show', $b['customer']))->assertOk()->assertSee('PRIVATE-SUB-B')->assertSee('PRIVATE-INVOICE-B')->assertDontSee('PRIVATE-SUB-A');
    }

    public function test_foreign_details_and_mutations_are_denied_in_every_demo_module(): void
    {
        $this->partner();
        $a = $this->scenario['parks']['A'];
        foreach ([['customers.show', $a['customer']], ['customers.edit', $a['customer']], ['garage.subscriptions.show', $a['subscription']], ['garage.stays.show', $a['stay']], ['garage.stays.ticket', $a['stay']], ['invoices.show', $a['invoice']], ['invoices.xml', $a['invoice']], ['reservations.ticket', $a['reservation']]] as [$route, $model]) {
            $this->get(route($route, $model))->assertNotFound();
        }
        foreach ([['garage.stays.checkout', $a['stay']], ['garage.subscriptions.stripe.checkout', $a['subscription']], ['garage.payments.reverse', $a['garagePayment']], ['shuttles.trips.cancel', $a['trip']], ['invoices.submit', $a['invoice']]] as [$route, $model]) {
            $this->post(route($route, $model), [])->assertNotFound();
        }
        $this->delete(route('shuttles.assignments.destroy', $a['assignment']))->assertNotFound();
        $this->patch(route('customers.status', $a['customer']), ['is_active' => false])->assertNotFound();
        $this->assertTrue($a['customer']->fresh()->is_active);
        $this->assertSame('paid', $a['garagePayment']->fresh()->status->value);
        $this->assertDatabaseHas('shuttle_trip_assignments', ['id' => $a['assignment']->id]);
    }

    public function test_cross_management_references_are_rejected_before_saving(): void
    {
        $b = $this->partner();
        $a = $this->scenario['parks']['A'];
        $this->postJson(route('shuttles.assignments.store', $b['trip']), ['reservation_id' => $a['reservation']->id, 'passengers' => 1])->assertUnprocessable()->assertJsonValidationErrors('reservation_id');
        $this->post(route('customers.records.link', $b['customer']), ['kind' => 'reservation', 'record_id' => $a['reservation']->id])->assertNotFound();
        $this->postJson(route('garage.subscriptions.store'), [
            'customer_id' => $a['customer']->id, 'parking_id' => $b['parking']->id, 'parking_product_id' => $a['product']->id, 'garage_rate_id' => $a['rate']->id,
            'customer_name' => 'Test', 'license_plate' => 'TEST', 'starts_on' => today()->toDateString(), 'reserved_spots' => 1, 'payment_mode' => 'manual',
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'parking_product_id', 'garage_rate_id']);
    }

    public function test_owner_can_switch_modules_but_old_tab_cannot_save(): void
    {
        $a = $this->scenario['parks']['A']; $b = $this->scenario['parks']['B'];
        $this->actingAs($this->scenario['owner'])->withSession(['active_parking_id' => $a['parking']->id]);
        $this->get(route('customers.index'))->assertOk()->assertSee('Cliente privato A')->assertDontSee('Cliente privato B');
        $this->post(route('parking.switch'), ['parking_id' => $b['parking']->id])->assertRedirect(route('dashboard'));
        $this->post(route('customers.store'), ['_parking_context' => $a['parking']->id])->assertStatus(409);
        $this->get(route('customers.index'))->assertOk()->assertSee('Cliente privato B')->assertDontSee('Cliente privato A');
    }

    public function test_simulated_subscription_payment_stays_in_selected_management(): void
    {
        $b = $this->partner();
        $this->post(route('garage.subscriptions.stripe.checkout', $b['subscription']))->assertRedirect();
        $payment = GaragePayment::where('parking_id', $b['parking']->id)->where('provider', 'stripe')->firstOrFail();
        $this->post(route('garage.stripe.simulate', $payment), ['outcome' => 'success'])->assertRedirect();
        $this->assertSame('paid', $payment->fresh()->status->value);
        $this->assertFalse(GaragePayment::where('parking_id', $this->scenario['parks']['A']['parking']->id)->where('provider', 'stripe')->exists());
        $this->get(route('public.subscription-payment.result', $this->scenario['parks']['A']['subscription']->uuid))->assertNotFound();
    }

    public function test_customer_backfill_does_not_merge_matching_identities_across_managements(): void
    {
        foreach ($this->scenario['parks'] as $park) {
            $park['reservation']->update(['customer_id' => null, 'customer_name' => 'Stesso nome demo', 'customer_email' => 'same@example.test', 'license_plate' => 'SAMEPLATE']);
        }
        app(CustomerBackfillService::class)->run();
        $a = $this->scenario['parks']['A']['reservation']->fresh();
        $b = $this->scenario['parks']['B']['reservation']->fresh();
        $this->assertNotSame($a->customer_id, $b->customer_id);
        $this->assertSame(2, Customer::where('email', 'same@example.test')->count());
        $this->assertSame(0, app(CustomerBackfillService::class)->run()['created']);
    }

    public function test_management_text_and_stale_tab_message_are_translated(): void
    {
        $b = $this->partner();
        foreach (['it' => 'Utenti', 'en_GB' => 'Users', 'nl' => 'Gebruikers'] as $locale => $label) {
            $this->withSession(['locale' => $locale])->get(route('management.users.index'))->assertOk()->assertSee($label);
            if ($locale !== 'it') {
                $message = trans('Il parcheggio attivo è cambiato. Ricarica la pagina prima di salvare.', [], $locale);
                $this->postJson(route('customers.store'), ['_parking_context' => 0])->assertStatus(409)->assertJsonPath('message', $message);
            }
        }
    }

    private function signedStripeEvent(string $route, string $secret, string $type, array $object)
    {
        $payload = json_encode(['id' => 'evt_demo_isolation', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        return $this->call('POST', $route, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload);
    }

    public function test_signed_garage_checkout_requires_matching_management_session_and_paid_status(): void
    {
        config(['demo.enabled' => false, 'payments.stripe.webhook_secret' => 'initial-test-secret']);
        $a = $this->scenario['parks']['A'];
        $b = $this->scenario['parks']['B'];
        $b['management']->forceFill(['payment_settings' => ['enabled' => true, 'stripe' => ['webhook_secret' => 'b-test-secret']]])->save();
        $payment = app(\App\Services\GaragePaymentService::class)->createStripePendingPayment($b['subscription'], today()->startOfMonth(), today()->endOfMonth());
        $payment->update(['provider_session_id' => 'cs_garage_b']);
        $object = ['id' => 'cs_garage_b', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 8000, 'currency' => 'eur', 'metadata' => ['garage_payment_id' => (string) $payment->id]];
        $route = route('management.webhooks.stripe', $b['management']->public_key);

        $this->signedStripeEvent(route('webhooks.stripe'), 'initial-test-secret', 'checkout.session.completed', $object)->assertOk();
        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->signedStripeEvent($route, 'initial-test-secret', 'checkout.session.completed', $object)->assertStatus(400);
        $this->signedStripeEvent($route, 'b-test-secret', 'checkout.session.completed', array_replace($object, ['payment_status' => 'unpaid']))->assertOk();
        $this->signedStripeEvent($route, 'b-test-secret', 'checkout.session.completed', array_replace($object, ['id' => 'cs_wrong']))->assertOk();
        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->signedStripeEvent($route, 'b-test-secret', 'checkout.session.completed', $object)->assertOk();
        $this->assertSame('paid', $payment->fresh()->status->value);
        $this->assertSame(today()->endOfMonth()->toDateString(), $b['subscription']->fresh()->paid_through->toDateString());
        $this->assertNull($a['subscription']->fresh()->paid_through);
        $this->signedStripeEvent($route, 'b-test-secret', 'checkout.session.completed', $object)->assertOk();
        $this->assertSame(1, GaragePayment::where('provider', 'stripe')->count());
        $this->assertFalse(app(ParkingContext::class)->restricted);
    }

    public function test_recurring_stripe_events_update_only_the_endpoint_management(): void
    {
        config(['demo.enabled' => false, 'payments.stripe.webhook_secret' => 'initial-test-secret']);
        $b = $this->scenario['parks']['B'];
        $b['management']->forceFill(['payment_settings' => ['enabled' => true, 'stripe' => ['webhook_secret' => 'b-test-secret']]])->save();
        $b['subscription']->update(['stripe_subscription_id' => 'sub_b']);
        $invoice = ['id' => 'in_b', 'object' => 'invoice', 'subscription' => 'sub_b', 'amount_paid' => 8000, 'currency' => 'eur', 'lines' => ['data' => [['period' => ['start' => today()->startOfMonth()->timestamp, 'end' => today()->addMonthNoOverflow()->startOfMonth()->timestamp]]]]];
        $route = route('management.webhooks.stripe', $b['management']->public_key);
        $this->signedStripeEvent(route('webhooks.stripe'), 'initial-test-secret', 'invoice.paid', $invoice)->assertOk();
        $this->assertFalse(GaragePayment::where('provider_payment_id', 'in_b')->exists());
        $this->signedStripeEvent($route, 'b-test-secret', 'invoice.paid', $invoice)->assertOk();
        $this->signedStripeEvent($route, 'b-test-secret', 'invoice.paid', $invoice)->assertOk();
        $payment = GaragePayment::where('provider_payment_id', 'in_b')->sole();
        $this->assertSame($b['parking']->id, $payment->parking_id);
        $this->assertSame('paid', $payment->status->value);
        $this->signedStripeEvent($route, 'b-test-secret', 'customer.subscription.deleted', ['id' => 'sub_b', 'object' => 'subscription', 'status' => 'canceled'])->assertOk();
        $this->assertSame('cancelled', $b['subscription']->fresh()->status->value);
        $this->assertSame('active', $this->scenario['parks']['A']['subscription']->fresh()->status->value);
        $this->assertFalse(app(ParkingContext::class)->restricted);
    }

    public function test_customer_history_follows_selected_parking_even_within_same_management(): void
    {
        $b = $this->scenario['parks']['B'];
        $other = $b['management']->parkings()->create(['name' => 'Altro parcheggio B di test', 'total_spots' => 10, 'is_active' => true]);
        $product = $other->products()->create(['name' => 'Posto test', 'code' => 'auto_open', 'capacity' => 10, 'price' => 10, 'is_active' => true]);
        $listing = $other->listings()->create(['platform_id' => $b['platform']->id, 'is_active' => true]);
        $reservation = $b['reservation']->replicate();
        $reservation->fill(['parking_id' => $other->id, 'parking_product_id' => $product->id, 'parking_listing_id' => $listing->id, 'external_id' => 'OTHER-B-HISTORY']);
        $reservation->save();
        $context = app(ParkingContext::class);
        $context->run($b['parking'], function () use ($b) {
            $references = app(CustomerHistoryService::class)->query($b['customer'])->pluck('reference')->all();
            $this->assertContains($b['reservation']->external_id, $references);
            $this->assertNotContains('OTHER-B-HISTORY', $references);
        });
        $context->run($other, function () use ($b) {
            $this->assertTrue(Customer::whereKey($b['customer']->id)->exists());
            $this->assertSame(['OTHER-B-HISTORY'], app(CustomerHistoryService::class)->query($b['customer'])->pluck('reference')->all());
        });
    }

    public function test_new_management_validation_uses_the_selected_language(): void
    {
        $this->partner();
        foreach (['it', 'en_GB', 'nl'] as $locale) {
            $this->withSession(['locale' => $locale])->postJson(route('parkings.store'), ['name' => 'Nuovo test', 'total_spots' => 10, 'capacity_mode' => 'shared', 'separate_management' => 1])->assertUnprocessable()
                ->assertJsonPath('errors.management_name.0', trans('Indica il nome della nuova gestione.', [], $locale));
            $this->postJson(route('management.users.store'), ['name' => 'Test', 'email' => 'new-test@example.test', 'role' => 'staff', 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()
                ->assertJsonPath('errors.password.0', trans('Inserisci una password di almeno 12 caratteri per il nuovo account.', [], $locale));
        }
    }

    public function test_legacy_demo_seeder_refuses_to_replace_existing_management_accounts(): void
    {
        $count = \App\Models\User::count();
        try {
            app(ParkingContext::class)->run($this->scenario['parks']['A']['parking'], fn () => $this->seed(\Database\Seeders\DemoScenarioSeeder::class));
            $this->fail('An installed environment must not be reseeded.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('fresh demo installation', $e->getMessage());
        }
        $this->assertSame($count, \App\Models\User::count());
        $this->assertTrue($this->scenario['partner']->managements()->whereKey($this->scenario['parks']['B']['management']->id)->exists());
    }
}
