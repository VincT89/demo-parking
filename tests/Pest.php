<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function invoicingScenario(array $settingOverrides = []): array
{
    $parking = \App\Models\Parking::query()->create([
        'name' => 'Demo Airport Parking',
        'address' => 'Demo address',
        'total_spots' => 100,
        'is_active' => true,
    ]);
    $platform = \App\Models\Platform::query()->create([
        'name' => 'Website',
        'slug' => 'website',
        'is_active' => true,
    ]);
    $product = \App\Models\ParkingProduct::query()->create([
        'parking_id' => $parking->id,
        'name' => 'Open-air car',
        'code' => 'car_open',
        'capacity' => 100,
        'price' => 61,
        'is_active' => true,
    ]);
    $listing = \App\Models\ParkingListing::query()->create([
        'parking_id' => $parking->id,
        'platform_id' => $platform->id,
        'name' => 'Direct website',
        'is_active' => true,
    ]);
    $reservation = \App\Models\Reservation::query()->create([
        'parking_id' => $parking->id,
        'parking_listing_id' => $listing->id,
        'parking_product_id' => $product->id,
        'external_id' => 'DEMO-INVOICE-001',
        'customer_name' => 'Sample Customer',
        'customer_email' => 'sample@example.test',
        'license_plate' => 'DE123MO',
        'starts_at' => now(),
        'ends_at' => now()->addDays(3),
        'spots' => 1,
        'passengers_count' => 2,
        'status' => 'confirmed',
        'price' => 61,
    ]);
    $settings = \App\Models\ParkingSetting::query()->create([
        'parking_id' => $parking->id,
        'invoice_mode' => 'simulator',
        'invoice_prefix' => 'DEMO',
        'next_invoice_number' => 1,
        'default_vat_rate' => 22,
        'prices_include_vat' => true,
        ...$settingOverrides,
    ]);

    return compact('parking', 'platform', 'product', 'listing', 'reservation', 'settings');
}

function invoiceCustomerData(): array
{
    return [
        'customer_type' => 'person',
        'customer_name' => 'Sample Customer',
        'customer_vat_country' => 'IT',
        'customer_vat_number' => null,
        'customer_fiscal_code' => 'RSSMRA80A01H501U',
        'customer_recipient_code' => '0000000',
        'customer_pec' => null,
        'customer_address' => 'Via di Prova 10',
        'customer_postal_code' => '00100',
        'customer_city' => 'Roma',
        'customer_province' => 'RM',
        'customer_country' => 'IT',
    ];
}
