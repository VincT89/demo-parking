<?php

namespace Tests\Feature;

use App\Exports\ReservationsExport;
use App\Integrations\AdapterRegistry;
use App\Integrations\Adapters\ParkosAdapter;
use App\Jobs\SyncListingJob;
use App\Models\Management;
use App\Models\User;
use App\Services\PaymentConfiguration;
use App\Tenancy\ParkingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ManagementFixtures;
use Tests\TestCase;

class ManagementIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $legacyFixtures = false;

    private array $fixtures;

    private array $a;

    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = ManagementFixtures::create();
        $this->a = $this->fixtures['parks']['A'];
        $this->b = $this->fixtures['parks']['B'];
    }

    private function asPartner(): self
    {
        return $this->actingAs($this->fixtures['partner']);
    }

    private function payload(array $data = []): array
    {
        return ['_parking_context' => $this->b['parking']->id, ...$data];
    }

    public function test_partner_sees_only_the_second_parking_and_its_operational_data(): void
    {
        $this->asPartner();
        foreach (['dashboard', 'reservations.index', 'calendar', 'analytics', 'alerts', 'platforms.index', 'parkings.index', 'sync-logs.index', 'management.activity'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('Parcheggio A (test)')->assertDontSee('Prenotazione A riservata')->assertDontSee('Registro privato A');
        }
        $this->get(route('reservations.index'))->assertSee('Prenotazione B riservata');
        $this->get(route('management.users.index'))->assertSee('Socio test')->assertSee('Cliente test');
    }

    public function test_owner_switches_context_without_aggregating_reservations(): void
    {
        $this->actingAs($this->fixtures['owner'])->get(route('reservations.index'))->assertSee('Prenotazione A riservata')->assertDontSee('Prenotazione B riservata');
        $this->post(route('parking.switch'), ['parking_id' => $this->b['parking']->id])->assertRedirect(route('dashboard'));
        $this->get(route('reservations.index'))->assertSee('Prenotazione B riservata')->assertDontSee('Prenotazione A riservata');
        $this->get(route('dashboard'))->assertViewHas('physicalOccupied', 1)->assertViewHas('physicalTotal', 20);
    }

    public function test_partner_cannot_switch_to_another_management_or_read_its_records(): void
    {
        $this->asPartner()->post(route('parking.switch'), ['parking_id' => $this->a['parking']->id])->assertNotFound();
        foreach ([route('reservations.show', $this->a['reservation']), route('reservations.edit', $this->a['reservation']), route('parkings.edit', $this->a['parking']), route('platforms.edit', $this->a['apiPlatform'])] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->get(route('calendar.data', ['parking_id' => $this->a['parking']->id]))->assertNotFound();
    }

    public function test_cross_management_writes_are_rejected_and_leave_data_unchanged(): void
    {
        $this->asPartner();
        $this->delete(route('reservations.destroy', $this->a['reservation']), $this->payload())->assertNotFound();
        $this->post(route('reservations.mark-paid', $this->a['reservation']), $this->payload())->assertNotFound();
        $this->post(route('reservations.toggle-movement', $this->a['reservation']), $this->payload(['type' => 'entered', 'value' => true]))->assertNotFound();
        $this->delete(route('platforms.mappings.destroy', $this->a['mapping']), $this->payload())->assertNotFound();
        $this->assertEquals('confirmed', $this->a['reservation']->fresh()->status->value);
        $this->assertEquals('pending', $this->a['payment']->fresh()->status);
        $this->assertTrue($this->a['mapping']->fresh()->is_active);
    }

    public function test_foreign_product_ids_cannot_be_attached_to_the_current_parking(): void
    {
        $this->asPartner()->post(route('platforms.mappings.store', $this->b['apiPlatform']), $this->payload([
            'parking_product_id' => $this->a['product']->id, 'external_ref' => 'forged',
        ]))->assertSessionHasErrors('parking_product_id');
        $this->post(route('parkings.allocations.store', $this->b['parking']), $this->payload([
            'parking_product_id' => $this->a['product']->id, 'allocation_type' => 'internal_use',
            'spots' => 1, 'starts_at' => now()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ]))->assertSessionHasErrors('parking_product_id');
    }

    public function test_old_tabs_and_requests_missing_a_context_cannot_save(): void
    {
        $this->actingAs($this->fixtures['owner'])->post(route('parking.switch'), ['parking_id' => $this->b['parking']->id])->assertRedirect();
        $this->post(route('reservations.mark-paid', $this->b['reservation']), ['_parking_context' => $this->a['parking']->id])->assertStatus(409);
        $this->post(route('reservations.mark-paid', $this->b['reservation']))->assertStatus(409);
        $this->assertEquals('pending', $this->b['payment']->fresh()->status);
    }

    public function test_global_admin_role_does_not_grant_access_without_membership(): void
    {
        $outsider = User::factory()->create(['role' => 'admin']);
        $this->actingAs($outsider)->get(route('dashboard'))->assertForbidden();
        $this->post(route('parking.switch'), ['parking_id' => $this->a['parking']->id])->assertNotFound();
    }

    public function test_membership_role_is_used_instead_of_the_global_role(): void
    {
        $this->b['management']->users()->updateExistingPivot($this->fixtures['owner']->id, ['role' => 'staff']);
        $this->actingAs($this->fixtures['owner'])->withSession(['active_parking_id' => $this->b['parking']->id])->get(route('platforms.index'))->assertForbidden();
        $this->get(route('reservations.index'))->assertOk();
    }

    public function test_revoking_membership_invalidates_existing_sessions(): void
    {
        $this->asPartner()->get(route('dashboard'))->assertOk();
        $this->b['management']->users()->detach($this->fixtures['partner']->id);
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_both_administrators_can_record_payments_and_activity_is_scoped(): void
    {
        $this->asPartner()->post(route('reservations.mark-paid', $this->b['reservation']), $this->payload())->assertRedirect();
        $this->assertEquals('paid', $this->b['payment']->fresh()->status);
        $this->assertEquals('pending', $this->a['payment']->fresh()->status);
        $this->get(route('management.activity'))->assertOk()->assertSee('Socio test')->assertDontSee('Operazione A');
        $this->assertDatabaseHas('activity_logs', ['parking_id' => $this->b['parking']->id, 'user_id' => $this->fixtures['partner']->id, 'subject_type' => 'Payment', 'action' => 'updated']);
    }

    public function test_new_accounts_receive_only_the_selected_management(): void
    {
        $this->asPartner()->post(route('management.users.store'), $this->payload([
            'name' => 'Nuovo operatore test', 'email' => 'nuovo@example.test', 'role' => 'staff',
            'password' => 'New-testing-password-2026', 'password_confirmation' => 'New-testing-password-2026',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', 'nuovo@example.test')->firstOrFail();
        $this->assertEquals([$this->b['management']->id], $user->managements()->pluck('managements.id')->all());
    }

    public function test_other_management_memberships_and_the_last_admin_cannot_be_removed(): void
    {
        $outsider = User::factory()->create();
        $this->a['management']->users()->attach($outsider, ['role' => 'staff']);
        $this->asPartner()->put(route('management.users.update', $outsider->id), $this->payload(['role' => 'admin']))->assertNotFound();
        $this->b['management']->users()->detach($this->fixtures['owner']->id);
        $this->delete(route('management.users.destroy', $this->fixtures['partner']->id), $this->payload())->assertSessionHasErrors('role');
    }

    public function test_creating_a_separate_management_grants_access_only_to_its_creator(): void
    {
        $this->actingAs($this->fixtures['owner'])->post(route('parkings.store'), [
            '_parking_context' => $this->a['parking']->id, 'name' => 'Parcheggio C (test)', 'total_spots' => 50,
            'capacity_mode' => 'shared', 'is_active' => true, 'separate_management' => true, 'management_name' => 'Gestione C (test)',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $management = Management::where('name', 'Gestione C (test)')->firstOrFail();
        $this->assertEquals([$this->fixtures['owner']->id], $management->users()->pluck('users.id')->all());
        $this->assertFalse($management->uses_legacy_payments);
        $this->assertFalse($management->platforms()->firstOrFail()->uses_legacy_credentials);
    }

    public function test_exports_keep_the_selected_parking_even_outside_the_request_context(): void
    {
        $response = $this->asPartner()->get(route('calendar.day.export', ['type' => 'entries', 'date' => now()->toDateString()]))->assertOk();
        $this->assertStringContainsString('Prenotazione B riservata', $response->streamedContent());
        $this->assertStringNotContainsString('Prenotazione A riservata', $response->streamedContent());
        $export = new ReservationsExport(parkingId: $this->b['parking']->id);
        $this->assertEquals([$this->b['reservation']->id], $export->query()->pluck('id')->all());
        $this->assertEquals(0, (new ReservationsExport)->query()->count());
    }

    public function test_sync_dispatch_is_limited_to_the_selected_parking(): void
    {
        Queue::fake();
        $this->asPartner()->post(route('platforms.future-sync'), $this->payload())->assertRedirect();
        Queue::assertPushed(SyncListingJob::class, 1);
        Queue::assertPushed(SyncListingJob::class, fn ($job) => $job->listing->id === $this->b['apiListing']->id);
    }

    public function test_product_mappings_resolve_within_the_listing_parking(): void
    {
        $adapter = app(ParkosAdapter::class);
        $this->assertEquals($this->a['product']->id, $adapter->resolveProduct($this->a['apiListing'], 'same-product-code')->id);
        $this->assertEquals($this->b['product']->id, $adapter->resolveProduct($this->b['apiListing'], 'same-product-code')->id);
    }

    public function test_an_unconfigured_management_never_uses_existing_credentials(): void
    {
        config(['services.parkos.enabled' => true, 'services.parkos.client_secret' => 'private-global-secret']);
        Http::preventStrayRequests();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configura le credenziali');
        app(AdapterRegistry::class)->forPlatform($this->b['apiPlatform']);
    }

    public function test_shared_connection_identity_is_reserved_to_the_original_management_admin(): void
    {
        $platform = $this->b['apiPlatform'];
        $platform->uses_legacy_credentials = true;
        $platform->save();
        $this->asPartner()->put(route('platforms.listing.update', [$platform, $this->b['apiListing']]), $this->payload(['external_id' => 'merchant-A', 'is_active' => true]))->assertForbidden();
        $this->put(route('platforms.connection', $platform), $this->payload(['use_shared_connection' => true]))->assertForbidden();
        $this->assertEquals('merchant-B', $this->b['apiListing']->fresh()->external_id);
    }

    public function test_public_booking_is_scoped_and_internal_codes_do_not_expose_reservations(): void
    {
        $this->get(route('public.booking.form'))->assertOk()->assertSee('Posto A (test)')->assertDontSee('Posto B (test)');
        $this->get(route('public.booking.form', ['parking' => $this->b['parking']->public_booking_key]))->assertOk()->assertSee('Posto B (test)')->assertDontSee('Posto A (test)');
        $this->a['reservation']->update(['parking_listing_id' => $this->a['apiListing']->id]);
        $this->get(route('public.booking.success', $this->a['reservation']->external_id))->assertNotFound();
        $this->asPartner()->get(route('public.booking.success', $this->a['reservation']->external_id))->assertNotFound();
    }

    public function test_internal_context_callbacks_cannot_change_a_selected_parking(): void
    {
        $context = app(ParkingContext::class);
        $context->select($this->b['parking']);
        try {
            $context->run($this->a['parking'], fn () => $this->fail('Foreign context was entered.'));
            $this->fail('A foreign context should be rejected.');
        } catch (HttpException $exception) {
            $this->assertEquals(404, $exception->getStatusCode());
            $this->assertEquals($this->b['parking']->id, $context->parking->id);
        } finally {
            $context->clear();
        }
    }

    public function test_public_forms_reject_product_codes_from_another_parking(): void
    {
        $this->a['product']->update(['code' => 'private-a-only']);
        $this->postJson(route('public.booking.check'), [
            'parking' => $this->b['parking']->public_booking_key,
            'product_code' => 'private-a-only', 'arrival_date' => now()->addDay()->format('Y-m-d'),
            'arrival_time' => '10:00', 'departure_date' => now()->addDays(2)->format('Y-m-d'),
            'departure_time' => '10:00', 'spots' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_code');
    }

    public function test_online_payments_do_not_fall_back_to_another_management_account(): void
    {
        $this->b['reservation']->update(['status' => 'pending', 'expires_at' => now()->addMinutes(15)]);
        $this->post(route('public.booking.stripe.checkout', $this->b['reservation']->external_id))->assertStatus(503);
        $this->post(route('public.booking.paypal.order', $this->b['reservation']->external_id))->assertStatus(503);
        $settings = app(PaymentConfiguration::class)->forManagement($this->b['management']);
        $this->assertFalse($settings['enabled']);
    }

    public function test_provider_credentials_and_cached_tokens_are_separate(): void
    {
        config(['services.parkos.base_url' => 'https://provider.example.test', 'services.parkos.auth_path' => '/oauth/token', 'services.parkos.reservations_path' => '/reservations']);
        foreach (['a', 'b'] as $key) {
            $platform = $this->{$key}['apiPlatform'];
            $platform->connection_settings = ['client_id' => 'client-'.$key, 'client_secret' => 'secret-'.$key, 'username' => $key, 'password' => 'pass-'.$key];
            $platform->save();
        }
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'token-'.$request['username']], 200);
            }

            return Http::response(['data' => [], 'paginator' => ['total_pages' => 1]], 200);
        });
        foreach (['a', 'b', 'a'] as $key) {
            $adapter = app(AdapterRegistry::class)->forPlatform($this->{$key}['apiPlatform']);
            $adapter->fetchReservations($this->{$key}['apiListing'], now()->subDay(), now());
        }
        $authRequests = Http::recorded(fn ($request) => str_ends_with($request->url(), '/oauth/token'));
        $this->assertCount(2, $authRequests);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/reservations') && $request['merchant_id'] === 'merchant-A' && $request->hasHeader('Authorization', 'Bearer token-a'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/reservations') && $request['merchant_id'] === 'merchant-B' && $request->hasHeader('Authorization', 'Bearer token-b'));
        $this->assertStringNotContainsString('secret-a', DB::table('platforms')->where('id', $this->a['apiPlatform']->id)->value('connection_settings'));
    }

    public function test_secrets_are_never_rendered_or_flashed_after_validation_errors(): void
    {
        $platform = $this->b['apiPlatform'];
        $platform->connection_settings = ['client_secret' => 'hidden-secret-b'];
        $platform->save();
        $this->asPartner()->get(route('platforms.edit', $platform))->assertOk()->assertDontSee('hidden-secret-b');
        $this->put(route('platforms.connection', $platform), $this->payload(['credentials' => ['password' => str_repeat('x', 2001), 'client_secret' => 'never-flash-me']]))->assertSessionHasErrors('credentials.password');
        $this->assertNull(session()->getOldInput('credentials'));
    }

    public function test_partner_cannot_enumerate_or_attach_users_from_the_original_management(): void
    {
        $otherUser = User::factory()->create(['email' => 'private-user@example.test']);
        $this->a['management']->users()->attach($otherUser, ['role' => 'staff']);
        $this->asPartner()->post(route('management.users.store'), $this->payload([
            'name' => 'Attempt', 'email' => $otherUser->email, 'role' => 'staff',
        ]))->assertSessionHasErrors('email');
        $this->assertFalse($this->b['management']->users()->whereKey($otherUser->id)->exists());
    }

    public function test_default_webhook_cannot_confirm_a_payment_from_another_management(): void
    {
        config(['payments.stripe.webhook_secret' => 'test-webhook-secret']);
        $this->b['reservation']->update(['status' => 'pending', 'expires_at' => now()->addMinutes(15)]);
        $payment = $this->b['payment'];
        $payment->update(['provider' => 'stripe', 'provider_session_id' => 'cs_other_management']);
        $payload = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_other_management', 'object' => 'checkout.session', 'amount_total' => 3000, 'currency' => 'eur']]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'test-webhook-secret');
        $this->call('POST', route('webhooks.stripe'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();
        $this->assertEquals('pending', $payment->fresh()->status);
        $this->assertEquals('pending', $this->b['reservation']->fresh()->status->value);
    }

    public function test_management_webhook_uses_its_own_secret_and_payment_scope(): void
    {
        $management = $this->b['management'];
        $management->payment_settings = ['enabled' => true, 'stripe' => ['webhook_secret' => 'second-test-secret']];
        $management->save();
        $this->b['reservation']->update(['status' => 'pending', 'expires_at' => now()->addMinutes(15)]);
        $payment = $this->b['payment'];
        $payment->update(['provider' => 'stripe', 'provider_session_id' => 'cs_second_management']);
        $payload = json_encode(['id' => 'evt_second', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_second_management', 'object' => 'checkout.session', 'amount_total' => 3000, 'currency' => 'eur']]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'second-test-secret');
        $this->call('POST', route('management.webhooks.stripe', $management->public_key), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();
        $this->assertEquals('paid', $payment->fresh()->status);
        $this->assertEquals('confirmed', $this->b['reservation']->fresh()->status->value);
        $this->assertEquals('pending', $this->a['payment']->fresh()->status);
        $this->assertFalse(app(ParkingContext::class)->restricted);
    }
}
