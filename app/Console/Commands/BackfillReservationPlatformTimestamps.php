<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillReservationPlatformTimestamps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:backfill-platform-timestamps {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill first_seen_at, last_seen_at and platform_* timestamps for existing reservations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info("Starting backfill..." . ($dryRun ? " [DRY RUN]" : ""));

        $query = Reservation::with('parkingListing.platform')
            ->where(function ($q) {
                $q->whereNull('first_seen_at')
                  ->orWhereNull('last_seen_at');
            });

        $count = $query->count();
        $this->info("Found {$count} reservations to process.");

        $processed = 0;
        $updated = 0;

        $query->chunkById(200, function ($reservations) use ($dryRun, &$processed, &$updated) {
            foreach ($reservations as $reservation) {
                $processed++;
                $platformSlug = $reservation->parkingListing->platform->slug ?? '';
                $rawData = $reservation->raw_data ?? [];

                $updates = [];

                if (is_null($reservation->first_seen_at)) {
                    $updates['first_seen_at'] = $reservation->created_at;
                }
                
                if (is_null($reservation->last_seen_at)) {
                    $updates['last_seen_at'] = $reservation->updated_at;
                }

                if ($platformSlug === 'parkos') {
                    if (is_null($reservation->platform_created_at) && isset($rawData['created_at'])) {
                        $updates['platform_created_at'] = $this->parseDate($rawData['created_at']);
                    }
                    if (is_null($reservation->platform_updated_at) && isset($rawData['updated_at'])) {
                        $updates['platform_updated_at'] = $this->parseDate($rawData['updated_at']);
                    }
                    if (is_null($reservation->platform_cancelled_at) && isset($rawData['cancelled_at'])) {
                        $updates['platform_cancelled_at'] = $this->parseDate($rawData['cancelled_at']);
                    }
                } elseif ($platformSlug === 'parking-my-car') {
                    if (is_null($reservation->platform_created_at)) {
                        $val = $rawData['created'] ?? $rawData['created_dttm'] ?? $rawData['created_at'] ?? null;
                        if ($val) $updates['platform_created_at'] = $this->parseTimestamp($val);
                    }
                    if (is_null($reservation->platform_updated_at)) {
                        $val = $rawData['updated'] ?? $rawData['updated_dttm'] ?? $rawData['updated_at'] ?? null;
                        if ($val) $updates['platform_updated_at'] = $this->parseTimestamp($val);
                    }
                    if (is_null($reservation->platform_cancelled_at)) {
                        $val = $rawData['cancelled'] ?? $rawData['cancelled_dttm'] ?? $rawData['cancelled_at'] ?? $rawData['canceled'] ?? $rawData['canceled_dttm'] ?? $rawData['canceled_at'] ?? null;
                        if ($val) $updates['platform_cancelled_at'] = $this->parseTimestamp($val);
                    }
                }

                if (!empty($updates)) {
                    if (!$dryRun) {
                        $reservation->update($updates);
                    }
                    $updated++;
                }
            }
            
            $this->info("Processed {$processed}...");
        });

        $this->info("Backfill complete. Processed: {$processed}, Updated: {$updated}");
    }

    private function parseDate($value): ?Carbon
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse((string) $value, 'Europe/Rome');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseTimestamp($value): ?Carbon
    {
        if (empty($value)) return null;
        try {
            return is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value, 'Europe/Rome')
                : Carbon::parse((string) $value, 'Europe/Rome');
        } catch (\Exception $e) {
            return null;
        }
    }
}
