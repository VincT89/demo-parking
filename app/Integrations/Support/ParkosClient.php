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

    private ?array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings;
        $this->baseUrl = rtrim($this->setting('base_url') ?? '', '/');
        $this->timeout = (int) $this->setting('timeout', 20);
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
        $cacheKey = $this->setting('token_cache_key');

        return Cache::remember($cacheKey, $this->setting('token_cache_ttl'), function () {
            return $this->authenticate()['access_token'];
        });
    }

    public function authenticate(): array
    {
        $this->ensureConfigured();

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asForm()
            ->post($this->url($this->setting('auth_path')), [
                'grant_type' => 'password',
                'client_id' => $this->setting('client_id'),
                'client_secret' => $this->setting('client_secret'),
                'username' => $this->setting('username'),
                'password' => $this->setting('password'),
            ]);

        $response->throw();
        return $this->storeTokens($response->json());
    }

    private function paginateReservations(array $params): array
    {
        $records = [];
        $page = 1;
        $maxPages = 100;
        $url = $this->url($this->setting('reservations_path', '/v1/reservations'));

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
            $this->setting('token_cache_key'),
            $data['access_token'] ?? null,
            now()->addSeconds(
                (int) $this->setting('token_cache_ttl', 3300)
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
            if (blank($this->setting($key))) {
                throw new \RuntimeException("Parkos config mancante: {$key}");
            }
        }
    }
    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings === null
            ? config('services.parkos.'.$key, $default)
            : ($this->settings[$key] ?? $default);
    }
}
