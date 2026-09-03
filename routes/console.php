<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('sync:automatic')
    ->everyFiveMinutes()
    ->name('sync_all_active_listings')
    ->withoutOverlapping(10);

\Illuminate\Support\Facades\Schedule::command('app:cancel-expired-pending-reservations')
    ->everyMinute()
    ->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::command(
    'queue:work database --queue=default --stop-when-empty --tries=3 --timeout=360'
)
    ->everyMinute()
    ->name('queue_worker_drain_default')
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/queue-worker.log'));

Artisan::command('parkos:auth-test', function () {
    $client = app(\App\Integrations\Support\ParkosClient::class);

    $result = $client->authenticate();

    $this->info('Autenticazione Parkos OK');
    $this->line(json_encode($result, JSON_PRETTY_PRINT));
});

Artisan::command('parkos:bookings-test {merchant=1895}', function ($merchant) {
    $client = app(\App\Integrations\Support\ParkosClient::class);

    $from = now()->subDays(30);
    $to = now()->addDays(180);

    $records = $client->findBookingsByPeriodType($from, $to, 'arrival', $merchant);

    $this->info('Prenotazioni trovate: '.count($records));

    foreach (array_slice($records, 0, 10) as $record) {
        $this->line(json_encode([
            'code' => $record['code'] ?? null,
            'merchant_id' => $record['merchant_id'] ?? null,
            'parking_type' => $record['parking_type'] ?? null,
            'location_type' => $record['location_type'] ?? null,
            'arrival_date' => $record['arrival_date'] ?? null,
            'departure_date' => $record['departure_date'] ?? null,
        ], JSON_PRETTY_PRINT));
    }
});
