<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\Reservation;
use App\Models\Platform;
use App\Enums\ReservationStatus;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $parkings = Parking::active()->get();

        if ($parkings->isEmpty()) {
            abort(404, 'Nessun parcheggio attivo configurato nel sistema.');
        }

        $parkingIds = $parkings->pluck('id');

        $physicalTotal = $parkings->sum(function($p) {
            return $p->getComputedTotalSpots();
        });

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $weekEnd = Carbon::today()->addDays(7);
        $monthEnd = Carbon::today()->endOfMonth();

        // 1. Occupazione Fisica (FOTOGRAFIA DI OGGI sull'intero parco)
        $physicalOccupied = Reservation::whereIn('parking_id', $parkingIds)
            ->active()
            ->overlapping($today, $tomorrow)
            ->sum('spots');

        $allocatedSpots = \App\Models\ParkingCapacityAllocation::whereIn('parking_id', $parkingIds)
            ->active()
            ->overlapping($today, $tomorrow)
            ->sum('spots');

        $totalOccupied = $physicalOccupied + $allocatedSpots;
        $physicalPct = $physicalTotal > 0 ? round(($totalOccupied / $physicalTotal) * 100) : 0;

        // 2. Performance Commerciale per Canale (OGGI)
        $commercialPerformance = Platform::query()
            ->with([
                'listings.reservations' => function ($query) use ($today, $tomorrow, $parkingIds) {
                    $query->whereIn('parking_id', $parkingIds)->active()->overlapping($today, $tomorrow);
                },
                'listings'
            ])
            ->active()
            ->get()
            ->map(function ($platform) use ($physicalTotal) {
                // Posti generati da questa piattaforma OGGI
                $soldToday = $platform->listings->flatMap->reservations->sum('spots');

                return [
                    'platform' => $platform->name,
                    'sold_today' => $soldToday,
                    'impact_pct' => $physicalTotal > 0 ? round(($soldToday / $physicalTotal) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('sold_today')
            ->values();

        // 3. Ultime prenotazioni e movimenti operativi di oggi
        $latestReservations = Reservation::query()
            ->whereIn('parking_id', $parkingIds)
            ->where('starts_at', '>=', now()->startOfDay())
            ->with(['parkingListing.platform'])
            ->orderBy('starts_at', 'asc')
            ->take(5)
            ->get();

        $incomingReservationsToday = Reservation::query()
            ->whereIn('parking_id', $parkingIds)
            ->active()
            ->whereDate('starts_at', $today)
            ->count();

        $outgoingReservationsToday = Reservation::query()
            ->whereIn('parking_id', $parkingIds)
            ->active()
            ->whereDate('ends_at', $today)
            ->count();

        $dashboardIncomingReservationsToday = Reservation::query()
            ->whereIn('parking_id', $parkingIds)
            ->active()
            ->whereDate('starts_at', $today)
            ->with(['parking.settings', 'parkingProduct'])
            ->orderBy('starts_at', 'asc')
            ->get();

        $dashboardOutgoingReservationsToday = Reservation::query()
            ->whereIn('parking_id', $parkingIds)
            ->active()
            ->whereDate('ends_at', $today)
            ->with(['parking.settings', 'parkingProduct'])
            ->orderBy('ends_at', 'asc')
            ->get();


        // Contatori generali
        $stats = [
            'today_count' => Reservation::whereIn('parking_id', $parkingIds)->active()->overlapping($today, $tomorrow)->count(),
            'week_count' => Reservation::whereIn('parking_id', $parkingIds)->active()->overlapping($today, $weekEnd)->count(),
            'month_count' => Reservation::whereIn('parking_id', $parkingIds)->active()->overlapping($today, $monthEnd)->count(),
            'total_active' => Reservation::whereIn('parking_id', $parkingIds)->active()->count(),
            'cancelled_month' => Reservation::whereIn('parking_id', $parkingIds)->where('status', ReservationStatus::Cancelled->value)
                ->whereMonth('created_at', $today->month)
                ->count(),
        ];

        // Alerts (Futura & Analitica) - aggregati su tutti i parcheggi attivi
        $alerts = (new AlertService())->getAlertsForParkings($parkings);

        return view('dashboard', compact(
            'parkings',
            'physicalTotal',
            'physicalOccupied',
            'allocatedSpots',
            'physicalPct',
            'commercialPerformance',
            'latestReservations',
            'incomingReservationsToday',
            'outgoingReservationsToday',
            'dashboardIncomingReservationsToday',
            'dashboardOutgoingReservationsToday',
            'stats',
            'alerts'
        ));
    }

}
