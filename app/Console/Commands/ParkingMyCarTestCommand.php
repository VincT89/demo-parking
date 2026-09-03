<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Integrations\Support\ParkingMyCarClient;

class ParkingMyCarTestCommand extends Command
{
    protected $signature = 'parking-my-car:test';
    protected $description = 'Test ParkingMyCar integration endpoints interactively';

    public function handle(ParkingMyCarClient $client)
    {
        if (!config('services.parking_my_car.enabled')) {
            $this->error('ParkingMyCar integration is currently disabled in .env (PARKING_MY_CAR_ENABLED=false)');
            return 1;
        }

        $this->info('Testing ParkingMyCar Integration...');

        try {
            $this->info("\n1. Testing Authentication...");
            $authData = $client->authenticate();
            $this->line('Success! Received tokens.');
            $this->line('Access Token: ' . substr($authData['access_token'] ?? '', 0, 10) . '...');
            $this->line('Refresh Token: ' . substr($authData['refresh_token'] ?? '', 0, 10) . '...');

            $this->info("\n2. Testing Token Refresh...");
            $refreshData = $client->refreshToken();
            $this->line('Success! Token refreshed.');

            $this->info("\n3. Testing Get Parkings (" . config('services.parking_my_car.resources_path') . ")...");
            $parkings = $client->getParkings();
            $this->line('Success! Found ' . count($parkings) . ' parkings.');
            if (count($parkings) > 0) {
                $this->line('First parking preview:');
                $this->line(json_encode($parkings[0], JSON_PRETTY_PRINT));
            }

            $this->info("\n4. Testing Sync Endpoint (" . config('services.parking_my_car.reservations_update_path') . ")...");
            $from = now()->subHours(2);
            $to = now();
            $this->line("Fetching reservations modified from {$from} to {$to}...");
            $reservations = $client->findBookingsByModification($from, $to);
            $this->line('Success! Found ' . count($reservations) . ' reservations modified in the window.');
            if (count($reservations) > 0) {
                $this->line('First reservation preview:');
                $this->line(json_encode($reservations[0], JSON_PRETTY_PRINT));
            }

            $this->info("\n✅ All ParkingMyCar tests passed successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Error occurred:");
            $this->error($e->getMessage());

            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $this->error("HTTP Status: " . $e->response->status());
                $this->line("Response Body:");
                $this->line($e->response->body());
            }
            
            return 1;
        }
    }
}
