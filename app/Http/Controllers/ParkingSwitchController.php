<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use Illuminate\Http\Request;

class ParkingSwitchController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate(['parking_id' => ['required', 'integer']]);
        $parking = Parking::withoutGlobalScopes()->whereHas('management.users', fn ($query) => $query->whereKey($request->user()->id))
            ->findOrFail($validated['parking_id']);
        $request->session()->put('active_parking_id', $parking->id);
        $request->session()->forget(['dismissed_alerts', '_old_input', 'errors']);

        return redirect()->route($parking->is_active ? 'dashboard' : 'parkings.index');
    }
}
