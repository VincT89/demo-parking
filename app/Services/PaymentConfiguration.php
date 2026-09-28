<?php

namespace App\Services;

use App\Models\Management;
use App\Tenancy\ParkingContext;

class PaymentConfiguration
{
    public function forManagement(?Management $management = null): array
    {
        $management ??= app(ParkingContext::class)->management;
        if (! $management) {
            return ['enabled' => false];
        }

        return $management->uses_legacy_payments
            ? config('payments', [])
            : ($management->payment_settings ?? ['enabled' => false]);
    }

    public function requireProvider(string $provider): array
    {
        $settings = $this->forManagement();
        $required = $provider === 'stripe' ? ['secret_key'] : ['client_id', 'client_secret'];
        abort_unless($settings['enabled'] ?? false, 503, __('Pagamento online non configurato per questa gestione.'));
        foreach ($required as $key) {
            abort_if(blank($settings[$provider][$key] ?? null), 503, __('Pagamento online non configurato per questa gestione.'));
        }

        return $settings;
    }

    public function webhookManagement(?string $key): Management
    {
        return $key
            ? Management::where('public_key', $key)->firstOrFail()
            : Management::where('is_initial', true)->firstOrFail();
    }
}
