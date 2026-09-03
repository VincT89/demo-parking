<?php

namespace Database\Seeders;

use App\Models\ParkingListing;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $service  = new ReservationService(new AvailabilityService());
        $listings = ParkingListing::with('platform')->get();

        $customers = [
            ['name' => 'Mario Rossi',      'email' => 'mario.rossi@email.it',      'phone' => '333-1234567', 'plate' => 'AB123CD'],
            ['name' => 'Laura Bianchi',    'email' => 'laura.bianchi@email.it',    'phone' => '347-2345678', 'plate' => 'EF456GH'],
            ['name' => 'Giuseppe Verdi',   'email' => 'giuseppe.verdi@email.it',   'phone' => '366-3456789', 'plate' => 'IJ789KL'],
            ['name' => 'Anna Ferrari',     'email' => 'anna.ferrari@email.it',     'phone' => '380-4567890', 'plate' => 'MN012OP'],
            ['name' => 'Carlo Marino',     'email' => 'carlo.marino@email.it',     'phone' => '392-5678901', 'plate' => 'QR345ST'],
            ['name' => 'Sofia Esposito',   'email' => 'sofia.esposito@email.it',   'phone' => '340-6789012', 'plate' => 'UV678WX'],
            ['name' => 'Luca Romano',      'email' => 'luca.romano@email.it',      'phone' => '349-7890123', 'plate' => 'YZ901AB'],
            ['name' => 'Giulia Conti',     'email' => 'giulia.conti@email.it',     'phone' => '338-8901234', 'plate' => 'CD234EF'],
            ['name' => 'Roberto Mancini',  'email' => 'roberto.mancini@email.it',  'phone' => '347-9012345', 'plate' => 'GH567IJ'],
            ['name' => 'Federica Gallo',   'email' => 'federica.gallo@email.it',   'phone' => '366-0123456', 'plate' => 'KL890MN'],
            ['name' => 'Davide Ricci',     'email' => 'davide.ricci@email.it',     'phone' => '333-1111222', 'plate' => 'OP123QR'],
            ['name' => 'Martina Costa',    'email' => 'martina.costa@email.it',    'phone' => '347-2222333', 'plate' => 'ST456UV'],
            ['name' => 'Francesco Bruno',  'email' => 'francesco.bruno@email.it',  'phone' => '380-3333444', 'plate' => 'WX789YZ'],
            ['name' => 'Valentina Greco',  'email' => 'valentina.greco@email.it',  'phone' => '392-4444555', 'plate' => 'AB012CD'],
            ['name' => 'Stefano Lombardi', 'email' => 'stefano.lombardi@email.it', 'phone' => '340-5555666', 'plate' => 'EF345GH'],
        ];

        // Prenotazioni passate — mese scorso
        $this->createBatch(
            $service, $listings, $customers,
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth(),
            30
        );

        // Prenotazioni passate — questa settimana (già concluse)
        $this->createBatch(
            $service, $listings, $customers,
            Carbon::now()->startOfWeek(),
            Carbon::now()->subDay(),
            8
        );

        // Prenotazioni attive oggi
        $this->createBatch(
            $service, $listings, $customers,
            Carbon::now()->subHours(6),
            Carbon::now()->addDays(5),
            6
        );

        // Prenotazioni future — prossima settimana
        $this->createBatch(
            $service, $listings, $customers,
            Carbon::now()->addDays(2),
            Carbon::now()->addDays(14),
            15
        );

        // Prenotazioni future — prossimo mese
        $this->createBatch(
            $service, $listings, $customers,
            Carbon::now()->addMonth()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth(),
            20
        );
    }

    private function createBatch(
        ReservationService $service,
        $listings,
        array $customers,
        Carbon $from,
        Carbon $to,
        int $count
    ): void {
        $attempts = 0;
        $created  = 0;

        while ($created < $count && $attempts < $count * 3) {
            $attempts++;

            $listing  = $listings->random();
            $customer = $customers[array_rand($customers)];

            $daysRange  = max(1, $from->diffInDays($to));
            $startOffset = rand(0, max(0, $daysRange - 1));
            $duration    = rand(2, 7);

            $startsAt = $from->copy()->addDays($startOffset)->setHour(rand(6, 10))->setMinute(0);
            $endsAt   = $startsAt->copy()->addDays($duration)->setHour(rand(18, 22))->setMinute(0);

            if ($endsAt->gt($to->copy()->addDays(2))) {
                continue;
            }

            $result = $service->create($listing, [
                'customer_name'  => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'license_plate'  => $customer['plate'],
                'starts_at'      => $startsAt,
                'ends_at'        => $endsAt,
                'spots'          => 1,
                'price'          => rand(25, 120) + (rand(0, 99) / 100),
                'external_id'    => strtoupper($listing->platform->slug[0]) . 'K-' . rand(10000, 99999),
                'raw_data'       => [
                    'source'     => $listing->platform->slug,
                    'imported_at' => now()->toISOString(),
                ],
            ]);

            if ($result->success) {
                $created++;
            }
        }

        $this->command->info("Batch completato: {$created}/{$count} prenotazioni create.");
    }
}