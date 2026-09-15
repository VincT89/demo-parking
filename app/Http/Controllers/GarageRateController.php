<?php

namespace App\Http\Controllers;

use App\Enums\GarageBillingUnit;
use App\Enums\GarageRateKind;
use App\Models\GarageRate;
use App\Models\Parking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GarageRateController extends Controller
{
    public function index(Request $request)
    {
        $parkings = Parking::query()->active()->orderBy('name')->get();
        abort_if($parkings->isEmpty(), 404, __('Nessun parcheggio attivo configurato nel sistema.'));

        $parking = $parkings->firstWhere('id', (int) $request->integer('parking_id')) ?? $parkings->first();
        $parking->load(['products' => fn ($query) => $query->orderBy('sort_order')]);

        $rates = GarageRate::query()
            ->where('parking_id', $parking->id)
            ->with('parkingProduct')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('garage.rates.index', [
            'parkings' => $parkings,
            'parking' => $parking,
            'rates' => $rates,
            'kinds' => GarageRateKind::cases(),
            'billingUnits' => GarageBillingUnit::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $this->assertCompatible($validated);

        GarageRate::query()->create($validated);

        return back()->with('success', __('Tariffa creata correttamente.'));
    }

    public function update(Request $request, GarageRate $garageRate)
    {
        $validated = $this->validated($request);
        $this->assertCompatible($validated);

        $garageRate->update($validated);

        return back()->with('success', __('Tariffa aggiornata correttamente.'));
    }

    public function destroy(GarageRate $garageRate)
    {
        $garageRate->update(['is_active' => false]);

        return back()->with('success', __('Tariffa disattivata.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'parking_id' => ['required', 'exists:parkings,id'],
            'parking_product_id' => [
                'required',
                Rule::exists('parking_products', 'id')->where('parking_id', $request->integer('parking_id')),
            ],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(GarageRateKind::class)],
            'billing_unit' => ['required', Rule::enum(GarageBillingUnit::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'minimum_units' => ['required', 'integer', 'min:1', 'max:365'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => false,
            'sort_order' => 0,
        ];
    }

    private function assertCompatible(array $data): void
    {
        $compatible = $data['kind'] === GarageRateKind::Subscription->value
            ? $data['billing_unit'] === GarageBillingUnit::Month->value
            : in_array($data['billing_unit'], [
                GarageBillingUnit::CalendarDay->value,
                GarageBillingUnit::TwentyFourHours->value,
            ], true);

        if (! $compatible) {
            throw ValidationException::withMessages([
                'billing_unit' => __('L’unità di calcolo non è compatibile con il tipo di tariffa.'),
            ]);
        }
    }
}
