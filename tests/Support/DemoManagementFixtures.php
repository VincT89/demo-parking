<?php

namespace Tests\Support;

use App\Models\{Customer, ElectronicInvoice, GaragePayment, GarageRate, ParkingSetting, ParkingStay, ParkingSubscription, ShuttleSetting, ShuttleTrip, ShuttleVehicle};
use App\Tenancy\ParkingContext;

class DemoManagementFixtures
{
    public static function create(): array
    {
        $fixtures = ManagementFixtures::create();
        foreach ($fixtures['parks'] as $letter => &$park) {
            $extra = app(ParkingContext::class)->run($park['parking'], function () use ($park, $letter, $fixtures) {
                $customer = Customer::create(['name' => 'Cliente privato '.$letter, 'email' => 'shared@example.test']);
                $customer->vehicles()->create(['license_plate' => 'PLATE'.$letter]);
                $park['reservation']->update(['customer_id' => $customer->id]);
                $rate = GarageRate::create(['parking_id' => $park['parking']->id, 'parking_product_id' => $park['product']->id, 'name' => 'Tariffa privata '.$letter, 'kind' => 'subscription', 'billing_unit' => 'month', 'price' => 80, 'currency' => 'EUR', 'minimum_units' => 1, 'is_active' => true]);
                $subscription = ParkingSubscription::create([
                    'parking_id' => $park['parking']->id, 'parking_product_id' => $park['product']->id, 'garage_rate_id' => $rate->id,
                    'customer_id' => $customer->id, 'customer_name' => $customer->name, 'customer_email' => $customer->email,
                    'reference' => 'PRIVATE-SUB-'.$letter, 'license_plate' => 'SUB'.$letter, 'starts_on' => today()->startOfMonth(),
                    'status' => 'active', 'reserved_spots' => 1, 'price' => 80, 'currency' => 'EUR', 'payment_mode' => 'stripe', 'created_by' => $fixtures['owner']->id,
                ]);
                $stay = ParkingStay::create([
                    'parking_id' => $park['parking']->id, 'parking_product_id' => $park['product']->id, 'parking_subscription_id' => $subscription->id,
                    'customer_id' => $customer->id, 'customer_name' => $customer->name, 'reference' => 'PRIVATE-STAY-'.$letter, 'license_plate' => 'STAY'.$letter,
                    'starts_at' => now()->subHour(), 'expected_ends_at' => now()->addHour(), 'status' => 'active', 'estimated_total' => 0, 'currency' => 'EUR', 'payment_status' => 'unpaid', 'created_by' => $fixtures['owner']->id,
                ]);
                $garagePayment = GaragePayment::create(['parking_id' => $park['parking']->id, 'parking_subscription_id' => $subscription->id, 'provider' => 'manual', 'method' => 'cash', 'status' => 'paid', 'amount' => 80, 'currency' => 'EUR', 'paid_at' => now(), 'recorded_by' => $fixtures['owner']->id]);
                $setting = ParkingSetting::create(['parking_id' => $park['parking']->id, 'invoice_mode' => 'simulator']);
                $invoice = ElectronicInvoice::create([
                    'reservation_id' => $park['reservation']->id, 'parking_id' => $park['parking']->id, 'number' => 'PRIVATE-INVOICE-'.$letter, 'sequence' => 1, 'year' => now()->year,
                    'provider_mode' => 'simulator', 'status' => 'draft', 'customer_name' => $customer->name, 'customer_address' => 'Indirizzo di test', 'customer_postal_code' => '00000', 'customer_city' => 'Test',
                    'taxable_amount' => 30, 'vat_rate' => 0, 'vat_amount' => 0, 'total_amount' => 30,
                ]);
                ShuttleSetting::create(['parking_id' => $park['parking']->id, 'is_enabled' => true, 'default_capacity' => 8]);
                $vehicle = ShuttleVehicle::create(['parking_id' => $park['parking']->id, 'name' => 'Navetta privata '.$letter, 'seats' => 8, 'is_active' => true]);
                $trip = ShuttleTrip::create(['parking_id' => $park['parking']->id, 'shuttle_vehicle_id' => $vehicle->id, 'direction' => 'outbound', 'scheduled_at' => now(), 'status' => 'proposed', 'capacity' => 8]);
                $assignment = $trip->assignments()->create(['reservation_id' => $park['reservation']->id, 'passengers' => 1]);
                return compact('customer', 'rate', 'subscription', 'stay', 'garagePayment', 'setting', 'invoice', 'vehicle', 'trip', 'assignment');
            });
            $park = array_merge($park, $extra);
        }
        return $fixtures;
    }
}
