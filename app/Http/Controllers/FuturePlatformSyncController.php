<?php

namespace App\Http\Controllers;

use App\Jobs\SyncListingJob;
use App\Models\ParkingListing;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;

class FuturePlatformSyncController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $from = Carbon::today()->startOfDay();
        $to = config('demo.enabled')
            ? Carbon::today()->addDays(config('demo.days_after', 23))->endOfDay()
            : Carbon::today()->addMonths(6)->endOfDay();

        $listings = ParkingListing::with('platform')
            ->where('is_active', true)
            ->whereHas('platform', function ($q) {
                $q->where('is_active', true)
                  ->where('slug', '!=', 'website');
            })
            ->get();

        foreach ($listings as $listing) {
            SyncListingJob::dispatch(
                $listing,
                $from->toDateTimeString(),
                $to->toDateTimeString(),
                'prossimi_6_mesi',
                'stay_period'
            );
        }

        return back()->with(
            'success',
            config('demo.enabled')
                ? __('Simulazione API dei prossimi giorni completata.')
                : __('Recupero prenotazioni da oggi ai prossimi 6 mesi avviato.')
        );
    }
}
