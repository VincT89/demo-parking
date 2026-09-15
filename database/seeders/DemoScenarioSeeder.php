<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use App\Models\ParkingSetting;
use App\Models\User;
use App\Models\GaragePayment;
use App\Models\GarageRate;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\ShuttleSetting;
use App\Models\ShuttleVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.enabled')) {
            return;
        }

        $parking = Parking::query()->firstOrFail();
        $parking->update([
            'name' => 'Parcheggio Aeroporto Demo',
            'address' => 'Via Dimostrazione 1, Bari',
            'total_spots' => 300,
            'capacity_mode' => 'per_product',
            'notes' => 'Ambiente dimostrativo con dati interamente sintetici.',
            'is_active' => true,
        ]);

        ParkingSetting::query()->updateOrCreate(
            ['parking_id' => $parking->id],
            [
                'invoice_mode' => 'simulator',
                'issuer_business_name' => 'Sodano Consulting Demo',
                'invoice_prefix' => 'DEMO',
                'next_invoice_number' => 1,
                'default_vat_rate' => 22,
                'prices_include_vat' => true,
                'ticket_format' => 'a6',
                'ticket_orientation' => 'portrait',
                'ticket_width_mm' => 80,
                'ticket_height_mm' => 60,
                'ticket_auto_open_on_exit' => true,
                'ticket_show_logo' => true,
                'ticket_title' => 'Ricevuta di ritiro veicolo',
                'ticket_footer' => 'Documento dimostrativo. Conservare fino al ritiro del veicolo.',
            ],
        );

        ParkingProduct::query()->where('parking_id', $parking->id)->each(function (ParkingProduct $product) {
            $product->update([
                'is_active' => true,
                'capacity' => match ($product->code) {
                    'auto_open' => 130,
                    'auto_covered' => 80,
                    'truck_open' => 50,
                    'truck_covered' => 40,
                    default => $product->capacity,
                },
            ]);
        });

        User::query()->delete();
        $admin = User::query()->create([
            'name' => 'Demo Administrator',
            'email' => 'demo@sodanoconsulting.it',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        User::query()->create([
            'name' => 'Demo Staff',
            'email' => 'staff-demo@sodanoconsulting.it',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $garageProduct = ParkingProduct::query()
            ->where('parking_id', $parking->id)
            ->where('code', 'auto_open')
            ->firstOrFail();

        $monthlyRate = GarageRate::query()->create([
            'parking_id' => $parking->id,
            'parking_product_id' => $garageProduct->id,
            'name' => 'Abbonamento mensile demo',
            'kind' => 'subscription',
            'billing_unit' => 'month',
            'price' => 99,
            'currency' => 'EUR',
            'grace_minutes' => 0,
            'minimum_units' => 1,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $dailyRate = GarageRate::query()->create([
            'parking_id' => $parking->id,
            'parking_product_id' => $garageProduct->id,
            'name' => 'Sosta giornaliera demo',
            'kind' => 'walk_in',
            'billing_unit' => 'twenty_four_hours',
            'price' => 15,
            'currency' => 'EUR',
            'grace_minutes' => 30,
            'minimum_units' => 1,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $subscription = ParkingSubscription::query()->create([
            'parking_id' => $parking->id,
            'parking_product_id' => $garageProduct->id,
            'garage_rate_id' => $monthlyRate->id,
            'reference' => 'ABB-DEMO-001',
            'customer_name' => 'Alex Demo',
            'customer_email' => 'abbonato-demo@example.test',
            'customer_phone' => '0000000000',
            'license_plate' => 'DEMO-ABB-01',
            'starts_on' => now()->startOfMonth()->toDateString(),
            'paid_through' => now()->endOfMonth()->toDateString(),
            'next_billing_on' => now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            'status' => 'active',
            'reserved_spots' => 1,
            'price' => $monthlyRate->price,
            'currency' => 'EUR',
            'payment_mode' => 'stripe',
            'notes' => 'Contratto e importi esclusivamente dimostrativi.',
            'created_by' => $admin->id,
        ]);

        GaragePayment::query()->create([
            'parking_id' => $parking->id,
            'parking_subscription_id' => $subscription->id,
            'provider' => 'manual',
            'method' => 'card',
            'status' => 'paid',
            'amount' => $monthlyRate->price,
            'currency' => 'EUR',
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->endOfMonth()->toDateString(),
            'recorded_by' => $admin->id,
            'paid_at' => now()->startOfMonth()->addHours(9),
            'raw_data' => ['demo' => true, 'source' => 'demo_seeder'],
        ]);

        ParkingStay::query()->create([
            'parking_id' => $parking->id,
            'parking_product_id' => $garageProduct->id,
            'parking_subscription_id' => $subscription->id,
            'reference' => 'SOSTA-DEMO-ABB',
            'customer_name' => $subscription->customer_name,
            'customer_email' => $subscription->customer_email,
            'customer_phone' => $subscription->customer_phone,
            'license_plate' => $subscription->license_plate,
            'starts_at' => now()->subHours(2),
            'expected_ends_at' => now()->addHours(6),
            'status' => 'active',
            'estimated_total' => 0,
            'currency' => 'EUR',
            'payment_status' => 'unpaid',
            'notes' => 'Ingresso abbonato dimostrativo.',
            'created_by' => $admin->id,
        ]);

        $completedStay = ParkingStay::query()->create([
            'parking_id' => $parking->id,
            'parking_product_id' => $garageProduct->id,
            'garage_rate_id' => $dailyRate->id,
            'reference' => 'SOSTA-DEMO-DAY',
            'customer_name' => 'Taylor Demo',
            'license_plate' => 'DEMO-DAY-01',
            'starts_at' => now()->subDay()->setTime(8, 0),
            'expected_ends_at' => now()->subDay()->setTime(18, 0),
            'ended_at' => now()->subDay()->setTime(17, 45),
            'status' => 'completed',
            'billing_unit' => $dailyRate->billing_unit->value,
            'unit_price' => $dailyRate->price,
            'estimated_total' => $dailyRate->price,
            'total_amount' => $dailyRate->price,
            'currency' => 'EUR',
            'payment_status' => 'paid',
            'notes' => 'Sosta giornaliera dimostrativa.',
            'created_by' => $admin->id,
            'closed_by' => $admin->id,
        ]);

        GaragePayment::query()->create([
            'parking_id' => $parking->id,
            'parking_stay_id' => $completedStay->id,
            'provider' => 'manual',
            'method' => 'cash',
            'status' => 'paid',
            'amount' => $dailyRate->price,
            'currency' => 'EUR',
            'recorded_by' => $admin->id,
            'paid_at' => $completedStay->ended_at,
            'raw_data' => ['demo' => true, 'source' => 'demo_seeder'],
        ]);

        ShuttleSetting::query()->create([
            'parking_id' => $parking->id,
            'is_enabled' => true,
            'default_capacity' => 8,
            'grouping_window_minutes' => 30,
            'turnaround_minutes' => 45,
            'outbound_offset_minutes' => 15,
            'return_offset_minutes' => 0,
        ]);

        ShuttleVehicle::query()->create([
            'parking_id' => $parking->id,
            'name' => 'Demo Shuttle 1',
            'license_plate' => 'DEMO-NAV-01',
            'seats' => 8,
            'is_active' => true,
            'notes' => 'Mezzo esclusivamente dimostrativo.',
        ]);

        PlatformProductMapping::query()->delete();

        $products = ParkingProduct::query()
            ->where('parking_id', $parking->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        Platform::query()->each(function (Platform $platform) use ($parking, $products) {
            $platform->update([
                'website' => null,
                'contact_email' => $platform->slug === 'website'
                    ? null
                    : 'demo-'.$platform->slug.'@example.test',
                'is_active' => true,
            ]);

            ParkingListing::query()->updateOrCreate(
                [
                    'parking_id' => $parking->id,
                    'platform_id' => $platform->id,
                ],
                [
                    'external_id' => $platform->slug === 'website'
                        ? null
                        : 'DEMO-'.strtoupper(str_replace('-', '_', $platform->slug)),
                    'is_active' => true,
                ]
            );

            if ($platform->slug === 'website') {
                return;
            }

            foreach ($products as $product) {
                PlatformProductMapping::query()->create([
                    'platform_id' => $platform->id,
                    'parking_product_id' => $product->id,
                    'external_ref' => sprintf('DEMO-%s-%s', strtoupper(str_replace('-', '_', $platform->slug)), strtoupper($product->code)),
                    'external_name' => "{$platform->name} Demo · {$product->name}",
                    'is_active' => true,
                ]);
            }
        });
    }
}
