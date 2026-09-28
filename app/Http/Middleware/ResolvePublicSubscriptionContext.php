<?php

namespace App\Http\Middleware;

use App\Models\ParkingSubscription;
use App\Tenancy\ParkingContext;
use Closure;
use Illuminate\Http\Request;

class ResolvePublicSubscriptionContext
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(ParkingContext::class);
        $context->clear();
        $subscription = ParkingSubscription::where('uuid', $request->route('uuid'))->with('parking.management')->firstOrFail();
        $parking = $subscription->parking;
        abort_unless($parking, 404);
        if ($request->user()) {
            abort_unless($request->user()->managements()->whereKey($parking->management_id)->exists(), 404);
        }
        return $context->run($parking, fn () => $next($request));
    }
}
