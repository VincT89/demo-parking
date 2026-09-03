<?php

namespace App\Http\Controllers;

use App\Actions\SyncListingAction;
use App\Models\ParkingListing;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class ManualPlatformSyncController extends Controller
{
    public function __invoke(SyncListingAction $action): RedirectResponse
    {
        $lock = Cache::lock('manual-platform-sync', 300);

        if (! $lock->get()) {
            return back()->with('warning', __('Sincronizzazione già in corso.'));
        }

        try {
            $listings = ParkingListing::with('platform')
                ->where('is_active', true)
                ->whereHas('platform', function ($q) {
                    $q->where('is_active', true)
                      ->where('slug', '!=', 'website');
                })
                ->get();

            $totals = [
                'created' => 0,
                'updated' => 0,
                'failed'  => 0,
                'skipped' => 0,
                'errors'  => [],
                'skipped_reasons' => [],
            ];

            foreach ($listings as $listing) {
                try {
                    $adapter = app(\App\Integrations\AdapterRegistry::class)
                        ->forPlatform($listing->platform);

                    [$from, $to] = $adapter->defaultSyncWindow();

                    $stats = $action->execute(
                        listing: $listing,
                        from: $from,
                        to: $to,
                        dryRun: false,
                        mode: 'modified'
                    );

                    foreach (['created', 'updated', 'failed', 'skipped'] as $key) {
                        $totals[$key] += $stats[$key];
                    }

                    $totals['errors'] = array_merge($totals['errors'], $stats['errors']);
                    $totals['skipped_reasons'] = array_merge(
                        $totals['skipped_reasons'],
                        $stats['skipped_reasons'] ?? []
                    );

                    $notes = array_merge($stats['errors'] ?? [], $stats['skipped_reasons'] ?? []);

                    SyncLog::create([
                        'platform_id' => $listing->platform_id,
                        'parking_listing_id' => $listing->id,
                        'source' => config('demo.enabled') ? 'manual_demo' : 'manual_live',
                        'status' => empty($stats['errors']) ? 'success' : 'failed',
                        'is_dry_run' => false,
                        'reservations_created' => $stats['created'],
                        'reservations_updated' => $stats['updated'],
                        'reservations_failed' => $stats['failed'],
                        'reservations_skipped' => $stats['skipped'],
                        'notes' => empty($notes) ? null : substr(implode("\n", $notes), 0, 1000),
                        'window_from' => $from,
                        'window_to' => $to,
                    ]);
                } catch (\Exception $e) {
                    $totals['failed']++;
                    $totals['errors'][] = "Errore Listing {$listing->id}: " . $e->getMessage();
                    
                    SyncLog::create([
                        'platform_id' => $listing->platform_id,
                        'parking_listing_id' => $listing->id,
                        'source' => config('demo.enabled') ? 'manual_demo' : 'manual_live',
                        'status' => 'failed',
                        'is_dry_run' => false,
                        'reservations_created' => 0,
                        'reservations_updated' => 0,
                        'reservations_failed' => 1,
                        'reservations_skipped' => 0,
                        'notes' => substr($e->getMessage(), 0, 1000),
                        'window_from' => now()->subHours(24),
                        'window_to' => now(),
                    ]);
                }
            }

            return back()->with(
                empty($totals['errors']) ? 'success' : 'warning',
                config('demo.enabled')
                    ? __('Simulazione API completata. Create: :created, aggiornate: :updated, annullate o saltate: :skipped, errori: :failed.', [
                        'created' => $totals['created'],
                        'updated' => $totals['updated'],
                        'skipped' => $totals['skipped'],
                        'failed' => $totals['failed'],
                    ])
                    : "Sync live completato. Create: {$totals['created']}, aggiornate: {$totals['updated']}, saltate: {$totals['skipped']}, errori: {$totals['failed']}."
            );
        } finally {
            $lock->release();
        }
    }
}
