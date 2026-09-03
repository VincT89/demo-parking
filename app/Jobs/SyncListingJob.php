<?php

namespace App\Jobs;

use App\Actions\SyncListingAction;
use App\Models\ParkingListing;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncListingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 180, 300];
    }

    public int $uniqueFor = 420;

    public function uniqueId(): string
    {
        return implode(':', [
            'sync-listing',
            $this->source,
            $this->listing->id,
            $this->mode,
            $this->from ?? 'default-from',
            $this->to ?? 'default-to',
        ]);
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync-listing-'.$this->listing->id))
                ->releaseAfter(60)
                ->expireAfter(420),
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly ParkingListing $listing,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly string $source = 'job',
        public readonly string $mode = 'modified',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SyncListingAction $action): void
    {
        $this->listing->loadMissing('platform');

        Log::info('SyncListingJob started', [
            'listing_id' => $this->listing->id,
            'platform_id' => $this->listing->platform_id,
            'platform_slug' => $this->listing->platform->slug ?? null,
            'source' => $this->source,
            'mode' => $this->mode,
            'from' => $this->from,
            'to' => $this->to,
            'attempt' => $this->attempts(),
        ]);

        if (! $this->listing->platform) {
            Log::warning('SyncListingJob skipped: missing platform', [
                'listing_id' => $this->listing->id,
                'platform_id' => $this->listing->platform_id,
                'source' => $this->source,
            ]);

            return;
        }

        if (! $this->listing->is_active || ! $this->listing->platform->is_active) {
            Log::info('SyncListingJob skipped: inactive listing or platform', [
                'listing_id' => $this->listing->id,
                'listing_active' => $this->listing->is_active,
                'platform_id' => $this->listing->platform_id,
                'platform_active' => $this->listing->platform->is_active,
                'source' => $this->source,
            ]);

            return;
        }

        if ($this->listing->platform->slug === 'website') {
            Log::info('SyncListingJob skipped: website platform', [
                'listing_id' => $this->listing->id,
                'platform_id' => $this->listing->platform_id,
                'platform_slug' => $this->listing->platform->slug,
                'source' => $this->source,
            ]);

            return;
        }

        $adapter = app(\App\Integrations\AdapterRegistry::class)
            ->forPlatform($this->listing->platform);

        if ($this->from && $this->to) {
            $from = Carbon::parse($this->from);
            $to = Carbon::parse($this->to);
        } else {
            [$from, $to] = $adapter->defaultSyncWindow();
        }

        $stats = $action->execute(
            $this->listing,
            $from,
            $to,
            dryRun: false,
            mode: $this->mode
        );

        $status = empty($stats['errors']) ? 'success' : 'failed';

        SyncLog::create([
            'platform_id'          => $this->listing->platform_id,
            'parking_listing_id'   => $this->listing->id,
            'source'               => $this->source,
            'status'               => $status,
            'is_dry_run'           => false,
            'reservations_created' => $stats['created'],
            'reservations_updated' => $stats['updated'],
            'reservations_failed'  => $stats['failed'],
            'reservations_skipped' => $stats['skipped'],
            'notes'                => empty($stats['errors']) && empty($stats['skipped_reasons']) ? null : substr(implode("\n", array_merge($stats['errors'] ?? [], $stats['skipped_reasons'] ?? [])), 0, 1000),
            'window_from'          => $from,
            'window_to'            => $to,
        ]);

        Log::info('SyncListingJob finished', [
            'listing_id' => $this->listing->id,
            'platform_id' => $this->listing->platform_id,
            'platform_slug' => $this->listing->platform->slug ?? null,
            'source' => $this->source,
            'mode' => $this->mode,
            'status' => $status,
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'failed' => $stats['failed'],
            'skipped' => $stats['skipped'],
            'errors_count' => count($stats['errors']),
            'attempt' => $this->attempts(),
        ]);

        $fatalErrors = array_values(array_filter(
            $stats['errors'],
            fn (string $error) => str_starts_with($error, 'Fatal Error:')
        ));

        if ($this->source === 'job' && ! empty($fatalErrors)) {
            throw new RuntimeException(implode("\n", $fatalErrors));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SyncListingJob permanently failed', [
            'listing_id' => $this->listing->id,
            'platform_id' => $this->listing->platform_id,
            'source' => $this->source,
            'mode' => $this->mode,
            'from' => $this->from,
            'to' => $this->to,
            'error' => $exception?->getMessage(),
        ]);
    }
}
