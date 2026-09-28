<?php

namespace App\Http\Middleware;

use App\Models\Parking;
use App\Models\Reservation;
use App\Tenancy\ParkingContext;
use Closure;
use Illuminate\Http\Request;

class ResolvePublicBookingContext
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(ParkingContext::class);
        $context->clear();
        $externalId = $request->route('externalId') ?? $request->route('uuid');
        if ($externalId) {
            $reservations = Reservation::where('external_id', $externalId)
                ->whereHas('parkingListing.platform', fn ($query) => $query->where('slug', 'website'))
                ->with('parking.management')->limit(2)->get();
            abort_unless($reservations->count() === 1, 404);
            $parking = $reservations->first()->parking;
        } else {
            $query = Parking::active();
            if ($request->filled('parking')) {
                $query->where('public_booking_key', $request->input('parking'));
            } else {
                $query->where('is_default_public', true);
            }
            $parking = $query->firstOrFail();
        }
        abort_unless($parking, 404);
        if ($request->user()) {
            abort_unless($request->user()->managements()->whereKey($parking->management_id)->exists(), 404);
        }
        $context->select($parking);
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
