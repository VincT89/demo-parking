<?php

namespace App\Console\Commands;

use App\Actions\SyncListingAction;
use App\Enums\ReservationStatus;
use App\Models\ParkingListing;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ResetDemoCommand extends Command
{
    protected $signature = 'demo:reset {--single-sync : Import only the initial simulated API response}';

    protected $description = 'Rebuild the isolated demo database and import a rolling month from simulated APIs';

    public function handle(SyncListingAction $action): int
    {
        if (! config('demo.enabled')) {
            $this->error('DEMO_MODE must be enabled before resetting demo data.');

            return self::FAILURE;
        }

        $this->components->info('Rebuilding the isolated demo database...');
        $exitCode = $this->call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        Cache::flush();

        $from = Carbon::today()->subDays(config('demo.days_before', 7))->startOfDay();
        $to = Carbon::today()->addDays(config('demo.days_after', 23))->endOfDay();
        $cycles = $this->option('single-sync') ? 1 : 2;
        $listings = ParkingListing::query()
            ->with('platform')
            ->where('is_active', true)
            ->whereHas('platform', fn ($query) => $query->where('is_active', true)->where('slug', '!=', 'website'))
            ->get();

        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            foreach ($listings as $listing) {
                $stats = $action->execute($listing, $from, $to, false, 'modified');

                SyncLog::query()->create([
                    'platform_id' => $listing->platform_id,
                    'parking_listing_id' => $listing->id,
                    'source' => 'demo_api',
                    'status' => empty($stats['errors']) ? 'success' : 'failed',
                    'is_dry_run' => false,
                    'reservations_created' => $stats['created'],
                    'reservations_updated' => $stats['updated'],
                    'reservations_failed' => $stats['failed'],
                    'reservations_skipped' => $stats['skipped'],
                    'notes' => empty($stats['errors']) ? 'Simulated API response' : substr(implode("\n", $stats['errors']), 0, 1000),
                    'window_from' => $from,
                    'window_to' => $to,
                ]);
            }
        }

        $this->decorateScenario();

        $this->components->info(sprintf(
            'Demo ready: %d synthetic reservations imported from %d simulated platforms.',
            Reservation::query()->count(),
            $listings->count(),
        ));
        $this->line('Login: demo@sodanoconsulting.it / password');

        return self::SUCCESS;
    }

    private function decorateScenario(): void
    {
        Reservation::query()->orderBy('starts_at')->get()->each(function (Reservation $reservation, int $index) {
            if ($reservation->status !== ReservationStatus::Cancelled) {
                $reservation->update([
                    'has_entered' => $reservation->starts_at->lte(now()),
                    'has_exited' => $reservation->ends_at->lt(now()),
                ]);
            }

            if ($reservation->status !== ReservationStatus::Cancelled && $index % 3 !== 0) {
                Payment::query()->updateOrCreate(
                    [
                        'provider' => 'demo',
                        'provider_payment_id' => 'DEMO-PAY-'.$reservation->id,
                    ],
                    [
                        'reservation_id' => $reservation->id,
                        'status' => 'paid',
                        'amount' => $reservation->price ?? 0,
                        'currency' => 'EUR',
                        'raw_data' => ['demo' => true, 'simulated' => true],
                        'paid_at' => $reservation->created_at ?? now(),
                    ]
                );
            }
        });

        Reservation::query()
            ->where('starts_at', '>', now())
            ->where('status', ReservationStatus::Confirmed->value)
            ->orderBy('starts_at')
            ->limit(4)
            ->get()
            ->each(fn (Reservation $reservation) => $reservation->update([
                'status' => ReservationStatus::Pending->value,
                'expires_at' => now()->addDays(30),
            ]));
    }
}
