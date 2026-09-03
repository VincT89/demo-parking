<?php

namespace App\Integrations\Support;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ParkingMyCarClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.parking_my_car.base_url') ?? '', '/');
        $this->timeout = (int) config('services.parking_my_car.timeout', 20);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withToken($this->accessToken());
    }

    protected function accessToken(): string
    {
        $cacheKey = config('services.parking_my_car.token_cache_key');

        return Cache::remember($cacheKey, config('services.parking_my_car.token_cache_ttl'), function () {
            return $this->authenticate()['access_token'];
        });
    }

    public function authenticate(): array
    {
        $this->ensureConfigured();

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asForm()
            ->post($this->url(config('services.parking_my_car.auth_path')), [
                'grant_type' => 'password',
                'client_id' => config('services.parking_my_car.client_id'),
                'client_secret' => config('services.parking_my_car.client_secret'),
                'username' => config('services.parking_my_car.username'),
                'password' => config('services.parking_my_car.password'),
            ]);

        $response->throw();

        $data = $response->json();

        return $this->storeTokens($data);
    }

    public function refreshToken(): array
    {
        $refreshToken = Cache::get(config('services.parking_my_car.refresh_token_cache_key'));

        if (!$refreshToken) {
            return $this->authenticate();
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asForm()
            ->post($this->url(config('services.parking_my_car.refresh_path')), [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.parking_my_car.client_id'),
                'client_secret' => config('services.parking_my_car.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

        if ($response->failed()) {
            return $this->authenticate();
        }

        return $this->storeTokens($response->json());
    }

    private function storeTokens(array $data): array
    {
        Cache::put(
            config('services.parking_my_car.refresh_token_cache_key'),
            $data['refresh_token'] ?? null,
            now()->addDays(30)
        );

        Cache::put(
            config('services.parking_my_car.token_cache_key'),
            $data['access_token'] ?? null,
            now()->addSeconds(
                (int) config('services.parking_my_car.token_cache_ttl', 3300)
            )
        );

        return $data;
    }

    public function getParkings(): array
    {
        $response = $this->request()->get($this->url(config('services.parking_my_car.resources_path')));
        $response->throw();

        return $response->json('parkings') ?? [];
    }

    public function findBookingsByModification(Carbon $from, Carbon $to): array
    {
        // PMC usa start_dttm/end_dttm (con doppia t)
        $response = $this->request()->get($this->url(config('services.parking_my_car.reservations_update_path')), [
            'start_dttm' => $from->format('Y-m-d H:i:s'),
            'end_dttm' => $to->format('Y-m-d H:i:s'),
        ]);

        $response->throw();

        $json = $response->json() ?? [];

        return $json['bookings']
            ?? $json['reservations']
            ?? $json['data']
            ?? (array_is_list($json) ? $json : []);
    }

    public function findBookingsByPeriod(Carbon $from, Carbon $to): array
    {
        $response = $this->request()->get(
            $this->url(config('services.parking_my_car.reservations_path', '/pmc_rest/bookings_resource')),
            [
                'start_dttm' => $from->format('Y-m-d H:i:s'),
                'end_dttm' => $to->format('Y-m-d H:i:s'),
            ]
        );

        $response->throw();

        if (config('services.parking_my_car.debug_sync')) {
            \Log::debug('PMC bookings_resource response', [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'keys' => array_keys($response->json() ?? []),
                'bookings_count' => count($response->json('bookings') ?? []),
                'data_count' => count($response->json('data') ?? []),
                'reservations_count' => count($response->json('reservations') ?? []),
                'total_count' => count($response->json() ?? []),
            ]);
        }

        $json = $response->json() ?? [];

        return $json['bookings']
            ?? $json['reservations']
            ?? $json['data']
            ?? (array_is_list($json) ? $json : []);
    }

    private function ensureConfigured(): void
    {
        foreach (['base_url', 'client_id', 'client_secret', 'username', 'password'] as $key) {
            if (blank(config("services.parking_my_car.$key"))) {
                throw new \RuntimeException("ParkingMyCar config mancante: {$key}");
            }
        }
    }
}
