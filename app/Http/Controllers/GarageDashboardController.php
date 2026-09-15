<?php

namespace App\Http\Controllers;

use App\Enums\GaragePaymentStatus;
use App\Enums\ParkingStayStatus;
use App\Models\GaragePayment;
use App\Models\Parking;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GarageDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $parkings = Parking::query()->active()->orderBy('name')->get();
        abort_if($parkings->isEmpty(), 404, __('Nessun parcheggio attivo configurato nel sistema.'));

        $parking = $parkings->firstWhere('id', (int) $request->integer('parking_id')) ?? $parkings->first();
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $stats = [
            'active_subscriptions' => ParkingSubscription::query()
                ->where('parking_id', $parking->id)
                ->reservingBetween($today, $tomorrow)
                ->count(),
            'reserved_subscription_spots' => ParkingSubscription::query()
                ->where('parking_id', $parking->id)
                ->reservingBetween($today, $tomorrow)
                ->sum('reserved_spots'),
            'active_stays' => ParkingStay::query()
                ->where('parking_id', $parking->id)
                ->where('status', ParkingStayStatus::Active->value)
                ->count(),
            'unpaid_stays' => ParkingStay::query()
                ->where('parking_id', $parking->id)
                ->whereNull('parking_subscription_id')
                ->where('status', ParkingStayStatus::Completed->value)
                ->where('payment_status', 'unpaid')
                ->count(),
            'month_payments' => GaragePayment::query()
                ->where('parking_id', $parking->id)
                ->where('status', GaragePaymentStatus::Paid->value)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];

        $latestSubscriptions = ParkingSubscription::query()
            ->where('parking_id', $parking->id)
            ->with(['parkingProduct', 'latestPayment'])
            ->latest()
            ->limit(5)
            ->get();

        $latestStays = ParkingStay::query()
            ->where('parking_id', $parking->id)
            ->with(['parkingProduct', 'subscription'])
            ->latest('starts_at')
            ->limit(5)
            ->get();

        return view('garage.index', compact(
            'parkings',
            'parking',
            'stats',
            'latestSubscriptions',
            'latestStays',
        ));
    }
}
