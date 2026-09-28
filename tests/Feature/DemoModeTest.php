<?php

use App\Models\Reservation;
use App\Models\ParkingListing;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\User;
use App\Actions\SyncListingAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

test('demo reset creates a rolling month of synthetic reservations without HTTP calls', function () {
    config()->set('demo.enabled', true);
    config()->set('demo.api_latency_ms', 0);

    Http::fake();

    $this->seed();

    $action = app(SyncListingAction::class);
    $from = today()->subDays(7)->startOfDay();
    $to = today()->addDays(23)->endOfDay();

    $syncResults = ParkingListing::query()
        ->with('platform')
        ->where('is_active', true)
        ->whereHas('platform', fn ($query) => $query->where('is_active', true)->where('slug', '!=', 'website'))
        ->get()
        ->map(fn (ParkingListing $listing) => $action->execute($listing, $from, $to));

    expect(Reservation::query()->count())->toBeGreaterThan(150);
    expect(Reservation::query()->where('starts_at', '<', today()->subDays(7))->count())->toBe(0);
    expect(Reservation::query()->where('starts_at', '>', today()->addDays(23)->endOfDay())->count())->toBe(0);
    expect(Reservation::query()->where('customer_email', 'not like', '%@example.test')->count())->toBe(0);
    expect(Reservation::query()->where('external_id', 'not like', 'DEMO-%')->count())->toBe(0);
    expect($syncResults->flatMap(fn (array $stats) => $stats['errors'])->all())->toBe([]);
    $this->seed(\Database\Seeders\CustomerSeeder::class);
    expect(\App\Models\Customer::count())->toBeGreaterThan(100);
    expect(Reservation::query()->whereNotNull('customer_id')->count())->toBeGreaterThan(150);
    expect(\App\Models\ParkingSubscription::whereNull('customer_id')->count())->toBe(0);
    expect(\App\Models\ParkingStay::whereNull('customer_id')->count())->toBe(0);
    $this->actingAs(User::query()->where('email', 'demo@sodanoconsulting.it')->firstOrFail())
        ->get(route('customers.index'))->assertOk()->assertDontSee('Showing')->assertSee('risultati');
    expect(Hash::check('password', User::query()->where('email', 'demo@sodanoconsulting.it')->value('password')))->toBeTrue();
    foreach (Parking::query()->get() as $parking) {
        expect($parking->products()->where('is_active', true)->sum('capacity'))->toBe($parking->total_spots);
    }

    Http::assertNothingSent();
});

test('demo login is minimal and exposes compact language controls', function () {
    config()->set('demo.enabled', true);

    $this->get('/login')
        ->assertOk()
        ->assertSee('demo@sodanoconsulting.it')
        ->assertSee('socio@sodanoconsulting.it')
        ->assertSee('Accesso A+B')
        ->assertSee('Accesso solo B')
        ->assertSee('>password<', false)
        ->assertSee('aria-label="Italiano (IT)"', false)
        ->assertSee('aria-label="English (EN)"', false)
        ->assertSee('aria-label="Nederlands (NL)"', false)
        ->assertSee('data-show-password="Mostra password"', false)
        ->assertSee('data-hide-password="Nascondi password"', false)
        ->assertDontSee('Parking Management Demo')
        ->assertDontSee('pm-demo-banner', false);
});

test('demo blocks real payment endpoints and handles webhooks locally', function () {
    config()->set('demo.enabled', true);

    $this->post('/booking/DEMO-TEST/stripe/checkout')->assertNotFound();
    $this->post('/booking/DEMO-TEST/paypal/order')->assertNotFound();
    $this->post('/booking/DEMO-TEST/paypal/capture')->assertNotFound();

    $this->postJson('/webhooks/stripe')->assertOk()->assertJson(['received' => true, 'demo' => true]);
    $this->postJson('/webhooks/paypal')->assertOk()->assertJson(['received' => true, 'demo' => true]);
});

test('language selector accepts only the configured demo locales', function () {
    $this->post(route('locale.update'), ['locale' => 'en_GB'])
        ->assertRedirect()
        ->assertSessionHas('locale', 'en_GB');

    $this->post(route('locale.update'), ['locale' => 'uk'])
        ->assertSessionHasErrors('locale');
});

test('unsupported configured locale falls back to the first supported locale', function () {
    config()->set('app.locale', 'en');

    $this->get('/login')
        ->assertOk()
        ->assertHeader('Content-Language', 'it')
        ->assertSee('fill="#009246"', false);
});


test('demo account labels follow the selected login language', function (string $locale, string $sharedLabel, string $restrictedLabel) {
    config()->set('demo.enabled', true);

    $this->withSession(['locale' => $locale])->get('/login')
        ->assertOk()
        ->assertSee($sharedLabel)
        ->assertSee($restrictedLabel)
        ->assertSee('demo@sodanoconsulting.it')
        ->assertSee('socio@sodanoconsulting.it')
        ->assertSee('id="demo-access-ab"', false)
        ->assertSee('id="demo-access-b"', false);
})->with([
    'Italian' => ['it', 'Accesso A+B', 'Accesso solo B'],
    'English' => ['en_GB', 'A+B access', 'B-only access'],
    'Dutch' => ['nl', 'Toegang tot A+B', 'Alleen toegang tot B'],
]);

test('demo credentials are absent from normal login in every language', function (string $locale) {
    config()->set('demo.enabled', false);

    $this->withSession(['locale' => $locale])->get('/login')
        ->assertOk()
        ->assertDontSee('pm-demo-accounts', false)
        ->assertDontSee('pm-demo-credentials', false)
        ->assertDontSee('demo@sodanoconsulting.it')
        ->assertDontSee('socio@sodanoconsulting.it');
})->with(['it', 'en_GB', 'nl']);
