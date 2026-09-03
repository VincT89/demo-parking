<?php

namespace App\Console\Commands;

use App\Actions\SyncListingAction;
use App\Models\ParkingListing;
use App\Models\Platform;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncPlatformsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:sync 
                            {--platform= : Slug of a specific platform to sync} 
                            {--listing= : Specific listing ID to sync (optional)}
                            {--from= : Start date (optional)}
                            {--to= : End date (optional)}
                            {--mode=modified : Sync mode (modified or stay_period)}
                            {--dry-run : Simulate sync without writing to DB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync reservations from external platforms';

    public function handle(SyncListingAction $action): int
    {
        $platformSlug = $this->option('platform');
        $listingId = $this->option('listing');
        $dryRun = $this->option('dry-run');
        $mode = $this->option('mode');

        $query = ParkingListing::with(['platform', 'parking'])
            ->active()
            ->whereHas('platform', function ($q) {
                $q->active()
                  ->where('slug', '!=', 'website');
            });

        if ($platformSlug) {
            $query->whereHas('platform', function ($q) use ($platformSlug) {
                $q->where('slug', $platformSlug);
            });
        }

        if ($listingId) {
            $query->where('id', $listingId);
        }

        $listings = $query->get();

        if ($listings->isEmpty()) {
            $this->info('No active listings found for the specified criteria.');
            return 0;
        }

        $this->info("Starting sync " . ($dryRun ? '[DRY-RUN]' : ''));

        foreach ($listings as $listing) {
            $this->info("Syncing {$listing->platform->name} - {$listing->parking->name} (Mode: {$mode})");

            $fromOption = $this->option('from');
            $toOption = $this->option('to');

            $adapter = app(\App\Integrations\AdapterRegistry::class)->forPlatform($listing->platform);
            [$defaultFrom, $defaultTo] = $adapter->defaultSyncWindow();

            $from = $fromOption ? Carbon::parse($fromOption) : $defaultFrom;
            $to = $toOption ? Carbon::parse($toOption) : $defaultTo;

            $this->info("Window: {$from->toDateTimeString()} to {$to->toDateTimeString()}");

            $stats = $action->execute($listing, $from, $to, $dryRun, $mode);

            $status = empty($stats['errors']) ? 'success' : 'failed';

            $notes = array_merge($stats['errors'] ?? [], $stats['skipped_reasons'] ?? []);

            \App\Models\SyncLog::create([
                'platform_id'          => $listing->platform_id,
                'parking_listing_id'   => $listing->id,
                'source'               => 'command',
                'status'               => $status,
                'is_dry_run'           => $dryRun,
                'reservations_created' => $stats['created'],
                'reservations_updated' => $stats['updated'],
                'reservations_failed'  => $stats['failed'],
                'reservations_skipped' => $stats['skipped'],
                'notes'                => empty($notes) ? null : substr(implode("\n", $notes), 0, 1000),
            ]);

            $this->table(
                ['Created', 'Updated', 'Skipped', 'Failed'],
                [[$stats['created'], $stats['updated'], $stats['skipped'], $stats['failed']]]
            );

            if (!empty($stats['errors'])) {
                $this->error("Errors encountered:");
                foreach ($stats['errors'] as $error) {
                    $this->line("- $error");
                }
            }
            $this->newLine();
        }

        $this->info('Sync completed.');
        return 0;
    }
}
