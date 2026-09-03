<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Http;

class PayPalService
{
    public function baseUrl(): string
    {
        return config('payments.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(
                config('payments.paypal.client_id'),
                config('payments.paypal.client_secret')
            )
            ->post($this->baseUrl() . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();

        return $response->json('access_token');
    }

    public function createOrder(Reservation $reservation): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl() . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $reservation->external_id,
                    'amount' => [
                        'currency_code' => config('payments.paypal.currency'),
                        'value' => number_format($reservation->price, 2, '.', ''),
                    ],
                ]],
            ]);

        $response->throw();

        return $response->json();
    }

    public function captureOrder(string $orderId): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('PayPal capture failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->json(),
                'raw' => $response->body(),
            ]);
        }

        $response->throw();

        return $response->json();
    }

    public function getOrder(string $orderId): array
    {
        $token = $this->accessToken();

        $response = Http::withToken($token)
            ->get($this->baseUrl() . "/v2/checkout/orders/{$orderId}");

        $response->throw();

        return $response->json();
    }
}
