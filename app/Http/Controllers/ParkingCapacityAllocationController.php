<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Parking;
use App\Models\ParkingCapacityAllocation;

class ParkingCapacityAllocationController extends Controller
{
    public function store(Request $request, Parking $parking)
    {
        $validated = $request->validate([
            'allocation_type' => ['required', 'string', 'in:rentcar,internal_use,partner,maintenance,other'],
            'parking_product_id' => ['nullable', 'exists:parking_products,id'],
            'spots'           => ['required', 'integer', 'min:1'],
            'starts_at'       => ['required', 'date'],
            'ends_at'         => ['required', 'date', 'after:starts_at'],
            'notes'           => ['nullable', 'string', 'max:255'],
            'is_active'       => ['boolean'],
        ]);

        if (empty($validated['parking_product_id']) && $parking->capacity_mode === 'per_product') {
            abort(422, 'Allocazioni globali non consentite per parcheggi per_product');
        }

        $validated['parking_id'] = $parking->id;
        $validated['is_active'] = $request->input('is_active', true);

        ParkingCapacityAllocation::create($validated);

        return back()->with('success', 'Capacità riservata creata correttamente.');
    }

    public function update(Request $request, Parking $parking, ParkingCapacityAllocation $allocation)
    {
        abort_unless($allocation->parking_id === $parking->id, 404);

        $validated = $request->validate([
            'allocation_type' => ['required', 'string', 'in:rentcar,internal_use,partner,maintenance,other'],
            'parking_product_id' => ['nullable', 'exists:parking_products,id'],
            'spots'           => ['required', 'integer', 'min:1'],
            'starts_at'       => ['required', 'date'],
            'ends_at'         => ['required', 'date', 'after:starts_at'],
            'notes'           => ['nullable', 'string', 'max:255'],
            'is_active'       => ['boolean'],
        ]);

        if (empty($validated['parking_product_id']) && $parking->capacity_mode === 'per_product') {
            abort(422, 'Allocazioni globali non consentite per parcheggi per_product');
        }

        $validated['is_active'] = $request->input('is_active', false);

        $allocation->update($validated);

        return back()->with('success', 'Capacità riservata aggiornata.');
    }

    public function destroy(Parking $parking, ParkingCapacityAllocation $allocation)
    {
        abort_unless($allocation->parking_id === $parking->id, 404);

        $allocation->update(['is_active' => false]);

        return back()->with('success', 'Capacità riservata eliminata.');
    }
}
