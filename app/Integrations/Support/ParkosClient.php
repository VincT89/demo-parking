<?php

namespace App\Integrations\Support;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ParkosClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.parkos.base_url') ?? '', '/');
        $this->timeout = (int) config('services.parkos.timeout', 20);
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
        $cacheKey = config('services.parkos.token_cache_key');

        return Cache::remember($cacheKey, config('services.parkos.token_cache_ttl'), function () {
            return $this->authenticate()['access_token'];
        });
    }

    public function authenticate(): array
    {
        $this->ensureConfigured();

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asForm()
            ->post($this->url(config('services.parkos.auth_path')), [
                'grant_type' => 'password',
                'client_id' => config('services.parkos.client_id'),
                'client_secret' => config('services.parkos.client_secret'),
                'username' => config('services.parkos.username'),
                'password' => config('services.parkos.password'),
            ]);

        $response->throw();
        return $this->storeTokens($response->json());
    }

    private function paginateReservations(array $params): array
    {
        $records = [];
        $page = 1;
        $maxPages = 100;
        $url = $this->url(config('services.parkos.reservations_path', '/v1/reservations'));

        do {
            $pageParams = array_merge($params, [
                'page' => $page,
            ]);

            $response = $this->request()->get($url, $pageParams);
            $response->throw();

            $data = $response->json();
            $items = array_values($data['data'] ?? []);

            $records = array_merge($records, $items);

            $paginator = $data['paginator'] ?? [];
            $totalPages = (int) ($paginator['total_pages'] ?? 0);
            $nextPageUrl = $paginator['next_page_url'] ?? null;

            if ($totalPages > 0 && $page >= $totalPages) {
                break;
            }

            if ($nextPageUrl === null && array_key_exists('next_page_url', $paginator)) {
                break;
            }

            if (count($items) === 0) {
                break;
            }

            $page++;

            if ($page > $maxPages) {
                throw new \RuntimeException('Parkos pagination limit exceeded.');
            }
        } while (true);

        return $records;
    }

    public function findBookingsByPeriodType(
        Carbon $from,
        Carbon $to,
        string $periodType,
        ?string $merchantId = null
    ): array {
        $params = [
            'from' => $from->toDateString(),
            'till' => $to->toDateString(),
            'period_type' => $periodType,
        ];

        if ($merchantId) {
            $params['merchant_id'] = $merchantId;
        }

        return $this->paginateReservations($params);
    }

    private function storeTokens(array $data): array
    {

        Cache::put(
            config('services.parkos.token_cache_key'),
            $data['access_token'] ?? null,
            now()->addSeconds(
                (int) config('services.parkos.token_cache_ttl', 3300)
            )
        );

        return $data;
    }

    public function findBookingsByCreation(Carbon $from, Carbon $to, ?string $merchantId = null): array
    {
        return $this->findBookingsByPeriodType($from, $to, 'created_at', $merchantId);
    }

    public function findBookingsByModification(Carbon $from, Carbon $to, ?string $merchantId = null): array
    {
        return $this->findBookingsByPeriodType($from, $to, 'updated_at', $merchantId);
    }

    public function findBookingsByCancellation(Carbon $from, Carbon $to, ?string $merchantId = null): array
    {
        return $this->findBookingsByPeriodType($from, $to, 'canceled_at', $merchantId);
    }

    private function ensureConfigured(): void
    {
        foreach (['base_url', 'client_id', 'client_secret', 'username', 'password'] as $key) {
            if (blank(config("services.parkos.$key"))) {
                throw new \RuntimeException("Parkos config mancante: {$key}");
            }
        }
    }
}
