<?php

namespace Database\Seeders;

use App\Models\{Management, Parking, User, Customer, GarageRate, ParkingSubscription, ParkingStay, ParkingSetting, Platform, ParkingListing, Reservation, ShuttleSetting, ShuttleVehicle};
use App\Tenancy\ParkingContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoManagementSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.enabled')) {
            throw new \RuntimeException('DemoManagementSeeder requires DEMO_MODE.');
        }
        $context = app(ParkingContext::class);
        if ($context->restricted) {
            throw new \RuntimeException('Run this seeder outside a selected parking context.');
        }
        DB::transaction(function () use ($context) {
            $owner = User::where('email', 'demo@sodanoconsulting.it')->firstOrFail();
            $initial = Management::where('is_initial', true)->firstOrFail();
            $initial->users()->syncWithoutDetaching([$owner->id => ['role' => 'admin']]);
            $management = Management::firstOrCreate(['name' => 'Gestione B Demo']);
            $management->users()->syncWithoutDetaching([$owner->id => ['role' => 'admin']]);
            $partner = User::firstOrCreate(['email' => 'socio@sodanoconsulting.it'], [
                'name' => 'Amministratore B Demo', 'password' => 'password',
            ]);
            // Never attach an existing unrelated account based only on its email.
            if (! $partner->wasRecentlyCreated && ! $management->users()->whereKey($partner->id)->exists()) {
                throw new \RuntimeException('The demo partner account already belongs to another setup.');
            }
            $management->users()->syncWithoutDetaching([$partner->id => ['role' => 'admin']]);
            $parking = $management->parkings()->firstOrCreate(['name' => 'Parcheggio B Demo'], [
                'total_spots' => 60, 'capacity_mode' => 'shared', 'is_active' => true,
                'notes' => 'Parcheggio e dati interamente dimostrativi.',
            ]);
            $context->run($parking, function () use ($parking, $owner) {
                $product = $parking->products()->firstOrCreate(['code' => 'auto_open'], [
                    'name' => 'Auto/Moto scoperto', 'capacity' => 60, 'price' => 15, 'is_active' => true,
                ]);
                $platform = Platform::firstOrCreate(['slug' => 'website'], ['name' => 'Sito Web', 'is_active' => true]);
                $listing = ParkingListing::firstOrCreate(['parking_id' => $parking->id, 'platform_id' => $platform->id], ['is_active' => true]);
                ParkingSetting::firstOrCreate(['parking_id' => $parking->id], ['invoice_mode' => 'simulator', 'issuer_business_name' => 'Gestione B Demo', 'ticket_format' => 'a6']);
                $monthly = GarageRate::firstOrCreate(['parking_id' => $parking->id, 'name' => 'Mensile B Demo'], [
                    'parking_product_id' => $product->id, 'kind' => 'subscription', 'billing_unit' => 'month',
                    'price' => 99, 'currency' => 'EUR', 'minimum_units' => 1, 'is_active' => true,
                ]);
                GarageRate::firstOrCreate(['parking_id' => $parking->id, 'name' => 'Giornaliero B Demo'], [
                    'parking_product_id' => $product->id, 'kind' => 'walk_in', 'billing_unit' => 'twenty_four_hours',
                    'price' => 15, 'currency' => 'EUR', 'minimum_units' => 1, 'is_active' => true,
                ]);
                $customer = Customer::firstOrCreate(['email' => 'cliente-b-demo@example.test'], ['name' => 'Cliente B Demo']);
                $customer->vehicles()->firstOrCreate(['license_plate' => 'DEMOB']);
                ParkingSubscription::firstOrCreate(['reference' => 'ABB-B-DEMO'], [
                    'parking_id' => $parking->id, 'parking_product_id' => $product->id, 'garage_rate_id' => $monthly->id,
                    'customer_id' => $customer->id, 'customer_name' => $customer->name, 'customer_email' => $customer->email,
                    'license_plate' => 'DEMOB', 'starts_on' => now()->startOfMonth(), 'status' => 'active',
                    'reserved_spots' => 1, 'price' => 99, 'currency' => 'EUR', 'payment_mode' => 'manual', 'created_by' => $owner->id,
                ]);
                ShuttleSetting::firstOrCreate(['parking_id' => $parking->id], ['is_enabled' => true, 'default_capacity' => 8, 'grouping_window_minutes' => 30, 'turnaround_minutes' => 45]);
                ShuttleVehicle::firstOrCreate(['parking_id' => $parking->id, 'name' => 'Navetta B Demo'], ['license_plate' => 'NAVBDEMO', 'seats' => 8, 'is_active' => true]);
                for ($day = 0; $day < 30; $day++) {
                    $starts = today()->subDays(7)->addDays($day)->setTime(9, 0);
                    Reservation::firstOrCreate(['parking_listing_id' => $listing->id, 'external_id' => 'DEMO-B-'.$starts->format('Ymd')], [
                        'parking_id' => $parking->id, 'parking_product_id' => $product->id,
                        'customer_id' => $customer->id, 'customer_name' => $customer->name, 'customer_email' => $customer->email,
                        'license_plate' => 'DEMOB', 'starts_at' => $starts, 'ends_at' => $starts->copy()->addDays(2),
                        'spots' => 1, 'passengers_count' => 3, 'price' => 30, 'status' => 'confirmed',
                        'raw_data' => ['demo' => true, 'source' => 'demo_management_seeder'],
                    ]);
                }
            });
        });
    }
}
