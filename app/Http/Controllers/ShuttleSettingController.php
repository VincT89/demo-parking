<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\ShuttleSetting;
use App\Models\ShuttleVehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShuttleSettingController extends Controller
{
    public function edit(Request $request)
    {
        $parkings = Parking::query()->active()->orderBy('name')->get();
        abort_if($parkings->isEmpty(), 404, __('Nessun parcheggio attivo configurato nel sistema.'));

        $parking = $parkings->firstWhere('id', (int) $request->integer('parking_id')) ?? $parkings->first();
        $settings = ShuttleSetting::query()->firstOrNew(['parking_id' => $parking->id]);
        $vehicles = ShuttleVehicle::query()
            ->where('parking_id', $parking->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('shuttles.settings', compact('parkings', 'parking', 'settings', 'vehicles'));
    }

    public function update(Request $request, Parking $parking)
    {
        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'default_capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'grouping_window_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'turnaround_minutes' => ['required', 'integer', 'min:0', 'max:360'],
            'outbound_offset_minutes' => ['required', 'integer', 'min:-180', 'max:180'],
            'return_offset_minutes' => ['required', 'integer', 'min:-180', 'max:180'],
        ]);

        ShuttleSetting::query()->updateOrCreate(
            ['parking_id' => $parking->id],
            [...$validated, 'is_enabled' => $request->boolean('is_enabled')],
        );

        return back()->with('success', __('Configurazione navetta aggiornata.'));
    }

    public function storeVehicle(Request $request)
    {
        ShuttleVehicle::query()->create($this->vehicleData($request));

        return back()->with('success', __('Navetta aggiunta.'));
    }

    public function updateVehicle(Request $request, ShuttleVehicle $vehicle)
    {
        $validated = $this->vehicleData($request, $vehicle);
        $vehicle->update($validated);

        return back()->with('success', __('Navetta aggiornata.'));
    }

    public function destroyVehicle(ShuttleVehicle $vehicle)
    {
        $vehicle->update(['is_active' => false]);

        return back()->with('success', __('Navetta disattivata.'));
    }

    private function vehicleData(Request $request, ?ShuttleVehicle $vehicle = null): array
    {
        $parkingId = $vehicle?->parking_id ?? $request->integer('parking_id');

        return $request->validate([
            'parking_id' => [$vehicle ? 'sometimes' : 'required', 'exists:parkings,id'],
            'name' => ['required', 'string', 'max:120'],
            'license_plate' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('shuttle_vehicles', 'license_plate')
                    ->where('parking_id', $parkingId)
                    ->ignore($vehicle),
            ],
            'seats' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]) + [
            'parking_id' => $parkingId,
            'is_active' => false,
        ];
    }
}
