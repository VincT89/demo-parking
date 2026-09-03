<?php

namespace App\Http\Controllers;

use App\Jobs\SyncListingJob;
use App\Models\ParkingListing;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HistoricalPlatformSyncController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        if (config('demo.enabled')) {
            $demoStart = Carbon::today()->subDays(config('demo.days_before', 7))->startOfDay();
            $demoEnd = Carbon::today()->addDays(config('demo.days_after', 23))->endOfDay();

            if ($from->lt($demoStart) || $to->gt($demoEnd)) {
                return back()->withErrors([
                    'to' => __('Nella demo puoi simulare soltanto il mese di dati disponibile, dal :from al :to.', [
                        'from' => $demoStart->format('d/m/Y'),
                        'to' => $demoEnd->format('d/m/Y'),
                    ]),
                ]);
            }
        }

        if ($from->diffInMonths($to) > 6) {
            return back()->withErrors([
                'to' => __('Il recupero storico non può superare 6 mesi per volta.'),
            ]);
        }

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
                'storico',
                'stay_period'
            );
        }

        return back()->with(
            'success',
            config('demo.enabled')
                ? __('Simulazione API del periodo selezionato completata.')
                : __('Recupero storico piattaforme avviato. Le prenotazioni verranno aggiornate a breve.')
        );
    }
}
