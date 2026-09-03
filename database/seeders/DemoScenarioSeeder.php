<?php

namespace Database\Seeders;

use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\Platform;
use App\Models\PlatformProductMapping;
use App\Models\ParkingSetting;
use App\Models\User;
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
        User::query()->create([
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
