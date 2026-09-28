<?php

namespace App\Http\Middleware;

use App\Models\Parking;
use App\Tenancy\ParkingContext;
use Closure;
use Illuminate\Http\Request;

class ResolveParkingContext
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(ParkingContext::class);
        $context->clear();
        $memberships = $request->user()->managements()->get();
        abort_if($memberships->isEmpty(), 403, __('Il tuo account non è associato a una gestione.'));

        $parkings = Parking::withoutGlobalScopes()
            ->whereIn('management_id', $memberships->modelKeys())
            ->orderByDesc('is_active')->orderBy('name')->get();
        $parking = $parkings->firstWhere('id', (int) $request->session()->get('active_parking_id')) ?? $parkings->first();
        $management = $parking ? $memberships->firstWhere('id', $parking->management_id) : $memberships->first();
        $context->select($parking, $management);
        $request->session()->put('active_parking_id', $parking?->id);
        $request->attributes->set('accessible_parkings', $parkings);

        try {
            $submittedContext = $request->input('_parking_context', $request->header('X-Parking-Context'));
            if ((! $request->isMethodSafe() || $submittedContext !== null) && (string) $submittedContext !== (string) ($parking?->id ?? 0)) {
                abort(409, __('Il parcheggio attivo è cambiato. Ricarica la pagina prima di salvare.'));
            }

            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
