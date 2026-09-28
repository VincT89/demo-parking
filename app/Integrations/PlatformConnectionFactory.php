<?php

namespace App\Integrations;

use App\Integrations\Adapters\ParkingMyCarAdapter;
use App\Integrations\Adapters\ParkosAdapter;
use App\Integrations\Adapters\VologioAdapter;
use App\Integrations\Contracts\PlatformAdapterInterface;
use App\Integrations\Support\FixturePayloadReader;
use App\Integrations\Support\ParkingMyCarClient;
use App\Integrations\Support\ParkosClient;
use App\Integrations\Support\RooshProviderClient;
use App\Models\Platform;
use Illuminate\Support\Arr;
use RuntimeException;

class PlatformConnectionFactory
{
    public function make(Platform $platform): PlatformAdapterInterface
    {
        $provider = match ($platform->slug) {
            'parkos' => 'parkos', 'parking-my-car' => 'parking_my_car', 'vologio' => 'vologio',
            default => throw new RuntimeException('Nessuna integrazione disponibile per questo canale.'),
        };
        $credentials = $platform->connection_settings;
        if (empty($credentials)) {
            throw new RuntimeException('Configura le credenziali della piattaforma per questa gestione prima di sincronizzare.');
        }
        // Only provider endpoints and operational settings can inherit defaults.
        // Credentials and cached tokens never fall back to another management.
        $settings = array_merge(Arr::only(config('services.'.$provider, []), [
            'base_url', 'timeout', 'auth_path', 'refresh_path', 'resources_path',
            'reservations_path', 'reservations_update_path', 'token_cache_ttl',
            'sync_lookback_hours', 'operational_past_days', 'operational_future_days', 'default_product_ref',
        ]), $credentials);
        $prefix = 'platform_'.$platform->id.'_'.hash('sha256', json_encode($credentials));
        $settings['token_cache_key'] = $prefix.'_access';
        $settings['refresh_token_cache_key'] = $prefix.'_refresh';
        $adapter = match ($provider) {
            'parkos' => new ParkosAdapter(new ParkosClient($settings), app(FixturePayloadReader::class)),
            'parking_my_car' => new ParkingMyCarAdapter(new ParkingMyCarClient($settings)),
            'vologio' => new VologioAdapter(new RooshProviderClient($settings)),
        };

        return $adapter->configure($settings);
    }
}
