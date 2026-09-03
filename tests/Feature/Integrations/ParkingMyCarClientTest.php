<?php

namespace Tests\Feature\Integrations;

use App\Integrations\Support\ParkingMyCarClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParkingMyCarClientTest extends TestCase
{

    use RefreshDatabase;

    private ParkingMyCarClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.parking_my_car', [
            'enabled' => true,
            'base_url' => 'https://api.parkingmycar.it',
            'auth_path' => '/oauth/token',
            'refresh_path' => '/oauth/token',
            'resources_path' => '/pmc_rest/parkings_resource',
            'reservations_update_path' => '/pmc_rest/bookings_resource_updated',
            'client_id' => 'test_client_id',
            'client_secret' => 'test_secret',
            'username' => 'test_user',
            'password' => 'test_pass',
            'timeout' => 20,
            'token_cache_key' => 'pmc_access_token_test',
            'refresh_token_cache_key' => 'pmc_refresh_token_test',
            'token_cache_ttl' => 3300,
        ]);

        $this->client = new ParkingMyCarClient();
    }

    public function test_authenticates_and_saves_token()
    {
        Http::fake([
            'https://api.parkingmycar.it/oauth/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 3600,
            ], 200),
        ]);

        Cache::forget('pmc_access_token_test');
        Cache::forget('pmc_refresh_token_test');

        $data = $this->client->authenticate();

        $this->assertEquals('new_access_token', $data['access_token']);
        $this->assertEquals('new_refresh_token', Cache::get('pmc_refresh_token_test'));
    }

    public function test_refreshes_token_if_present()
    {
        Cache::put('pmc_refresh_token_test', 'existing_refresh_token', now()->addDays(30));

        Http::fake([
            'https://api.parkingmycar.it/oauth/token' => function ($request) {
                if ($request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'existing_refresh_token') {
                    return Http::response([
                        'access_token' => 'refreshed_access_token',
                        'refresh_token' => 'new_refresh_token_2',
                    ], 200);
                }
                return Http::response([], 400);
            },
        ]);

        $data = $this->client->refreshToken();

        $this->assertEquals('refreshed_access_token', $data['access_token']);
    }

    public function test_fetches_bookings_by_last_update()
    {
        Cache::put('pmc_access_token_test', 'valid_access_token', 3300);

        Http::fake([
            'https://api.parkingmycar.it/pmc_rest/bookings_resource_updated*' => Http::response([
                'bookings' => [
                    ['id' => 1, 'status' => 'confirmed']
                ],
            ], 200),
        ]);

        $from = Carbon::parse('2026-06-09 10:00:00');
        $to = Carbon::parse('2026-06-09 12:00:00');

        $bookings = $this->client->findBookingsByModification($from, $to);

        $this->assertCount(1, $bookings);
        $this->assertEquals(1, $bookings[0]['id']);
    }
}
