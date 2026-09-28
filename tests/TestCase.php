<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Old tests describe a single management. Supply its explicit ownership in
    // fixtures only; production accounts never receive implicit access.
    protected bool $legacyFixtures = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->legacyFixtures || ! in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive(static::class))) {
            return;
        }
        $managementId = \App\Models\Management::orderBy('id')->value('id');
        \App\Models\Parking::creating(function ($parking) use ($managementId) {
            $parking->management_id ??= $managementId;
            if (! \App\Models\Parking::withoutGlobalScopes()->exists()) {
                $parking->is_default_public = true;
            }
        });
        \App\Models\Platform::creating(function ($platform) use ($managementId) {
            $platform->management_id ??= $managementId;
            $platform->uses_legacy_credentials ??= true;
        });
        \App\Models\Customer::creating(function ($customer) use ($managementId) {
            $customer->management_id ??= $managementId;
        });
        \App\Models\User::created(function ($user) use ($managementId) {
            $user->managements()->attach($managementId, ['role' => $user->role?->value ?? 'staff']);
        });
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->legacyFixtures && ! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'])
            && in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive(static::class))) {
            $server['HTTP_X_PARKING_CONTEXT'] ??= (string) ($this->app['session']->get('active_parking_id')
                ?? \App\Models\Parking::withoutGlobalScopes()->orderByDesc('is_active')->orderBy('name')->value('id') ?? 0);
        }
        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
