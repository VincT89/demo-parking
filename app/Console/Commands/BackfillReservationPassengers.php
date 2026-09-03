<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;

class BackfillReservationPassengers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:backfill-passengers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfills passengers_count for existing reservations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill for passengers_count...');

        Reservation::query()
            ->whereNull('passengers_count')
            ->with('parkingListing.platform')
            ->chunkById(200, function ($reservations) {
                foreach ($reservations as $reservation) {
                    $raw = $reservation->raw_data ?? [];

                    $slug = $reservation->parkingListing?->platform?->slug;

                    $value = match ($slug) {
                        'parkos' => $raw['persons'] ?? null,
                        'parking-my-car', 'parking_my_car', 'parkingmycar' => $raw['seats'] ?? null,
                        'vologio', 'roosh' => $raw['journey']['travelers'] ?? null,
                        default => null,
                    };

                    $reservation->update([
                        'passengers_count' => is_numeric($value) && (int) $value > 0
                            ? (int) $value
                            : 1,
                    ]);
                }
            });

        $this->info('Backfill completed successfully.');
    }
}
