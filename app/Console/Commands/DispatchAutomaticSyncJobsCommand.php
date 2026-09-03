<?php

namespace App\Console\Commands;

use App\Jobs\SyncListingJob;
use App\Models\ParkingListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchAutomaticSyncJobsCommand extends Command
{
    protected $signature = 'sync:automatic';

    protected $description = 'Dispatch automatic platform sync jobs for active listings';

    public function handle(): int
    {
        $listings = ParkingListing::with('platform')
            ->where('is_active', true)
            ->whereHas('platform', function ($q) {
                $q->where('is_active', true)
                  ->where('slug', '!=', 'website');
            })
            ->get();

        foreach ($listings as $listing) {
            SyncListingJob::dispatch($listing);
        }

        Log::info('Automatic sync dispatch completed', [
            'attempted_count' => $listings->count(),
        ]);

        $this->info("Automatic sync dispatch completed. Attempted listings: {$listings->count()}");

        return self::SUCCESS;
    }
}
