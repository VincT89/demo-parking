<?php

namespace App\Integrations\Support;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;

class RooshProviderClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $clientId;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.vologio.base_url') ?? '', '/');
        $this->apiKey = config('services.vologio.api_key') ?? '';
        $this->clientId = config('services.vologio.client_id') ?? '';
        $this->timeout = (int) config('services.vologio.timeout', 20);
    }

    protected function request(): PendingRequest
    {
        if (empty($this->baseUrl) || empty($this->apiKey) || empty($this->clientId)) {
            throw new \Exception("Vologio Base URL, API Key o Client ID non configurati.");
        }

        return Http::timeout($this->timeout)
            ->withHeaders([
                'X-ROOSH-API-KEY' => $this->apiKey,
                'X-ROOSH-CLIENT-ID' => $this->clientId,
                'Accept' => 'application/json',
            ]);
    }

    public function getServiceLocations(): array
    {
        $response = $this->request()->get("{$this->baseUrl}/service-locations/");
        $response->throw();
        return $response->json() ?? [];
    }

    public function getServices(): array
    {
        $response = $this->request()->get("{$this->baseUrl}/services/");
        $response->throw();
        return $response->json() ?? [];
    }

    public function getBooking(string $bookingId): ?array
    {
        $response = $this->request()->get("{$this->baseUrl}/bookings/{$bookingId}");
        
        if ($response->status() === 404) {
            return null;
        }
        
        $response->throw();
        return $response->json();
    }

    public function findBookingsByServiceId(array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        $allBookings = [];
        $page = 1;
        $limit = 100;
        $maxPages = 50;

        do {
            $response = $this->request()->get("{$this->baseUrl}/bookings/findByServiceId", [
                'service_id' => implode(',', $serviceIds),
                'limit' => $limit,
                'page' => $page,
            ]);

            $response->throw();
            $data = $response->json() ?? [];
            
            // Extract the actual array if it's wrapped in a key
            $items = $data['bookingsByServiceId'] ?? (isset($data[0]) ? $data : []);

            $allBookings = array_merge($allBookings, $items);

            if (count($items) < $limit || $page >= $maxPages) {
                break;
            }

            $page++;
        } while (true);

        return $allBookings;
    }

    public function findBookingsByModification(Carbon $start, Carbon $end): array
    {
        $allBookings = [];
        $page = 1;
        $limit = 100;
        $maxPages = 50;

        do {
            $response = $this->request()->get("{$this->baseUrl}/bookings/findByModification", [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'limit' => $limit,
                'page' => $page,
            ]);

            $response->throw();
            $data = $response->json() ?? [];
            
            // Extract the actual array if it's wrapped in a key
            $items = $data['bookingsByModification'] ?? (isset($data[0]) ? $data : []);

            $allBookings = array_merge($allBookings, $items);

            if (count($items) < $limit || $page >= $maxPages) {
                break;
            }

            $page++;
        } while (true);

        return $allBookings;
    }

    public function findBookingsByServiceLocationId(array $locationIds): array
    {
        if (empty($locationIds)) {
            return [];
        }

        $allBookings = [];
        $page = 1;
        $limit = 100;
        $maxPages = 50;

        do {
            $response = $this->request()->get("{$this->baseUrl}/bookings/findByServiceLocationId", [
                'service_location_id' => implode(',', $locationIds),
                'limit' => $limit,
                'page' => $page,
            ]);

            $response->throw();
            $data = $response->json() ?? [];
            
            $items = $data['bookingsByServiceLocationId'] ?? (isset($data[0]) ? $data : []);

            $allBookings = array_merge($allBookings, $items);

            if (count($items) < $limit || $page >= $maxPages) {
                break;
            }

            $page++;
        } while (true);

        return $allBookings;
    }
}
