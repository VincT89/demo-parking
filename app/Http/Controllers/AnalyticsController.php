<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\Reservation;
use App\Models\ParkingListing;
use App\Enums\ReservationStatus;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $now = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Exception $e) {
            $now = Carbon::now()->startOfMonth();
        }
        
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd   = $now->copy()->endOfMonth();
        
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd   = $now->copy()->subMonth()->endOfMonth();

        $prevMonthUrl = route('analytics', ['month' => $now->copy()->subMonth()->format('Y-m')]);
        $nextMonthUrl = route('analytics', ['month' => $now->copy()->addMonth()->format('Y-m')]);

        $activeParkingIds = \App\Models\Parking::active()->pluck('id');

        $platforms = Platform::with(['listings' => function ($q) use ($activeParkingIds) {
            $q->whereIn('parking_id', $activeParkingIds);
        }])->active()->get();

        $revenueStatuses = [
            ReservationStatus::Confirmed->value,
            ReservationStatus::Modified->value,
        ];

        $activeStatuses = [
            ReservationStatus::Confirmed->value,
            ReservationStatus::Modified->value,
            ReservationStatus::Pending->value,
        ];

        $channelStats = $platforms->map(function ($platform) use ($thisMonthStart, $thisMonthEnd, $lastMonthStart, $lastMonthEnd, $now, $revenueStatuses, $activeStatuses) {
            $listingIds = $platform->listings->pluck('id');

            // Questo mese
            $thisMonthRes = Reservation::whereIn('parking_listing_id', $listingIds)
                ->whereIn('status', $activeStatuses)
                ->whereBetween('starts_at', [$thisMonthStart, $thisMonthEnd])
                ->get();

            $thisRevenueRes = Reservation::whereIn('parking_listing_id', $listingIds)
                ->whereIn('status', $revenueStatuses)
                ->whereBetween('starts_at', [$thisMonthStart, $thisMonthEnd])
                ->get();

            // Mese scorso
            $lastMonthRes = Reservation::whereIn('parking_listing_id', $listingIds)
                ->whereIn('status', $activeStatuses)
                ->whereBetween('starts_at', [$lastMonthStart, $lastMonthEnd])
                ->get();

            $lastRevenueRes = Reservation::whereIn('parking_listing_id', $listingIds)
                ->whereIn('status', $revenueStatuses)
                ->whereBetween('starts_at', [$lastMonthStart, $lastMonthEnd])
                ->get();

            // Cancellate questo mese
            $cancelled = Reservation::whereIn('parking_listing_id', $listingIds)
                ->where('status', ReservationStatus::Cancelled->value)
                ->whereBetween('starts_at', [$thisMonthStart, $thisMonthEnd])
                ->count();

            $thisRevenue = $thisRevenueRes->sum('price');
            $lastRevenue = $lastRevenueRes->sum('price');
            $thisCount   = $thisMonthRes->count();
            $lastCount   = $lastMonthRes->count();
            $avgPrice    = $thisCount > 0 ? $thisRevenue / $thisCount : 0;

            $revenueChange = $lastRevenue > 0
                ? round((($thisRevenue - $lastRevenue) / $lastRevenue) * 100)
                : ($thisRevenue > 0 ? 100 : 0);

            $countChange = $lastCount > 0
                ? round((($thisCount - $lastCount) / $lastCount) * 100)
                : ($thisCount > 0 ? 100 : 0);

            // Trend annuale dell'anno selezionato (Gen-Dic)
            $trend = [];
            for ($month = 1; $month <= 12; $month++) {
                $mStart = $now->copy()->month($month)->startOfMonth();
                $mEnd   = $now->copy()->month($month)->endOfMonth();
                
                $mRes = Reservation::whereIn('parking_listing_id', $listingIds)
                    ->whereBetween('starts_at', [$mStart, $mEnd])
                    ->get();
                    
                $mRevenue = $mRes->whereIn('status', $revenueStatuses)->sum('price');
                $mCount = $mRes->whereIn('status', $activeStatuses)->count();

                $trend[] = [
                    'month'      => $mStart->isoFormat('MMM'),
                    'revenue'    => round($mRevenue, 2),
                    'count'      => $mCount,
                    'is_current' => $month === $now->month,
                ];
            }

            return [
                'platform'       => $platform->name,
                'slug'           => $platform->slug,
                'this_count'     => $thisCount,
                'last_count'     => $lastCount,
                'count_change'   => $countChange,
                'this_revenue'   => round($thisRevenue, 2),
                'last_revenue'   => round($lastRevenue, 2),
                'revenue_change' => $revenueChange,
                'avg_price'      => round($avgPrice, 2),
                'cancelled'      => $cancelled,
                'trend'          => $trend,
            ];
        });

        // Totali generali
        $totals = [
            'revenue'    => round($channelStats->sum('this_revenue'), 2),
            'count'      => $channelStats->sum('this_count'),
            'cancelled'  => $channelStats->sum('cancelled'),
            'avg_price'  => $channelStats->sum('this_count') > 0
                ? round($channelStats->sum('this_revenue') / $channelStats->sum('this_count'), 2)
                : 0,
        ];

        // Mese corrente label
        $monthLabel = $now->isoFormat('MMMM YYYY');

        return view('analytics', compact('channelStats', 'totals', 'monthLabel', 'prevMonthUrl', 'nextMonthUrl'));
    }
}