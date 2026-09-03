<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Integrations\Support\ParkosClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class ParkosClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.parkos.base_url', 'https://api.parkos.com');
        Config::set('services.parkos.client_id', 'client123');
        Config::set('services.parkos.client_secret', 'secret123');
        Config::set('services.parkos.username', 'user');
        Config::set('services.parkos.password', 'pass');
        Config::set('services.parkos.auth_path', '/oauth/token');
        Config::set('services.parkos.reservations_path', '/v1/reservations');
        Config::set('services.parkos.timeout', 20);
        Config::set('services.parkos.token_cache_key', 'test_parkos_token');
        Config::set('services.parkos.token_cache_ttl', 31500000);
    }

    public function test_find_bookings_handles_pagination()
    {
        Http::fake([
            'https://api.parkos.com/oauth/token' => Http::response([
                'access_token' => 'dummy_token',
            ], 200),
            
            'https://api.parkos.com/v1/reservations*' => function (\Illuminate\Http\Client\Request $request) {
                static $calls = 0;
                $calls++;

                if ($calls === 1) {
                    return Http::response([
                        'data' => [
                            1 => ['code' => 'PAGE1_RES1'],
                            2 => ['code' => 'PAGE1_RES2'],
                        ],
                        'paginator' => [
                            'next_page_url' => 'https://api.parkos.com/v1/reservations?page=2'
                        ]
                    ], 200);
                } elseif ($calls === 2) {
                    return Http::response([
                        'data' => [
                            3 => ['code' => 'PAGE2_RES1'],
                        ],
                        'paginator' => [
                            'next_page_url' => null
                        ]
                    ], 200);
                } else {
                    return Http::response([
                        'data' => [],
                        'paginator' => [
                            'next_page_url' => null
                        ]
                    ], 200);
                }
            }
        ]);

        $client = new ParkosClient();
        
        $from = Carbon::today();
        $to = Carbon::tomorrow();

        $records = $client->findBookingsByModification($from, $to, 'MERCHANT1');

        $this->assertCount(3, $records);
        $this->assertEquals('PAGE1_RES1', $records[0]['code']);
        $this->assertEquals('PAGE1_RES2', $records[1]['code']);
        $this->assertEquals('PAGE2_RES1', $records[2]['code']);
        
        Http::assertSentCount(3); // 1 for auth, 2 for pages
    }
}
