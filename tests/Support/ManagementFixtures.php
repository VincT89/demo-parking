<?php

namespace Tests\Support;

use App\Models\ActivityLog;
use App\Models\Management;
use App\Models\Reservation;
use App\Models\SyncLog;
use App\Models\User;

class ManagementFixtures
{
    public static function create(): array
    {
        $initial = Management::where('is_initial', true)->firstOrFail();
        $initial->update(['name' => 'Gestione A (test)']);
        $other = Management::create(['name' => 'Gestione B (test)']);
        $owner = User::factory()->create(['name' => 'Cliente test', 'email' => 'cliente@example.test', 'password' => 'Only-browser-fixture-2026', 'role' => 'admin']);
        $partner = User::factory()->create(['name' => 'Socio test', 'email' => 'socio@example.test', 'password' => 'Only-browser-fixture-2026', 'role' => 'admin']);
        $staff = User::factory()->create(['name' => 'Operatore test', 'email' => 'operatore@example.test', 'role' => 'staff']);
        $initial->users()->attach($owner, ['role' => 'admin']);
        $other->users()->attach($owner, ['role' => 'admin']);
        $other->users()->attach($partner, ['role' => 'admin']);
        $other->users()->attach($staff, ['role' => 'staff']);
        $parks = [];
        foreach ([$initial, $other] as $index => $management) {
            $letter = $index === 0 ? 'A' : 'B';
            $parking = $management->parkings()->create(['name' => 'Parcheggio '.$letter.' (test)', 'total_spots' => 20, 'capacity_mode' => 'shared', 'is_active' => true]);
            $parking->is_default_public = $index === 0;
            $parking->save();
            $product = $parking->products()->create(['name' => 'Posto '.$letter.' (test)', 'code' => 'auto_open', 'capacity' => 20, 'price' => 10, 'is_active' => true]);
            $platform = $management->platforms()->create(['name' => 'Sito '.$letter.' (test)', 'slug' => 'website', 'is_active' => true]);
            $listing = $parking->listings()->create(['platform_id' => $platform->id, 'is_active' => true]);
            $reservation = Reservation::create([
                'parking_id' => $parking->id, 'parking_product_id' => $product->id, 'parking_listing_id' => $listing->id,
                'external_id' => 'WEB-FIXTURE-'.$letter, 'customer_name' => 'Prenotazione '.$letter.' riservata',
                'customer_email' => strtolower($letter).'@example.test', 'customer_phone' => '0000000000',
                'license_plate' => 'TEST-'.$letter, 'starts_at' => now()->startOfDay(), 'ends_at' => now()->addDays(2)->endOfDay(),
                'spots' => 1, 'passengers_count' => 1, 'price' => 30, 'status' => 'confirmed',
            ]);
            $payment = $reservation->payments()->create(['provider' => 'onsite', 'status' => 'pending', 'amount' => 30, 'currency' => 'EUR']);
            $apiPlatform = $management->platforms()->create(['name' => 'Parkos '.$letter.' (test)', 'slug' => 'parkos', 'is_active' => true]);
            $apiListing = $parking->listings()->create(['platform_id' => $apiPlatform->id, 'external_id' => 'merchant-'.$letter, 'is_active' => true]);
            $mapping = $apiPlatform->productMappings()->create(['parking_product_id' => $product->id, 'external_ref' => 'same-product-code', 'is_active' => true]);
            $log = SyncLog::create(['platform_id' => $apiPlatform->id, 'parking_listing_id' => $apiListing->id, 'source' => 'test', 'status' => 'success', 'notes' => 'Registro privato '.$letter]);
            ActivityLog::create(['management_id' => $management->id, 'parking_id' => $parking->id, 'actor_name' => 'Operazione '.$letter, 'action' => 'created', 'subject_type' => 'Reservation', 'subject_id' => $reservation->id]);
            $parks[$letter] = compact('management', 'parking', 'product', 'platform', 'listing', 'reservation', 'payment', 'apiPlatform', 'apiListing', 'mapping', 'log');
        }

        return compact('owner', 'partner', 'staff', 'parks');
    }
}
