<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\ParkingSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $parkings = Parking::query()->orderBy('name')->get();
        abort_if($parkings->isEmpty(), 404, __('Nessun parcheggio configurato.'));

        $parking = $parkings->firstWhere('id', (int) $request->integer('parking_id')) ?? $parkings->first();
        $settings = ParkingSetting::query()->firstOrCreate(['parking_id' => $parking->id]);

        return view('admin.operational-settings.edit', compact('parkings', 'parking', 'settings'));
    }

    public function update(Request $request, Parking $parking)
    {
        $settings = ParkingSetting::query()->firstOrCreate(['parking_id' => $parking->id]);

        $validated = $request->validate([
            'invoice_mode' => ['required', Rule::in(['simulator', 'aruba_demo', 'aruba_production'])],
            'aruba_username' => ['nullable', 'string', 'max:255'],
            'aruba_password' => ['nullable', 'string', 'max:255'],
            'issuer_business_name' => ['nullable', 'string', 'max:80'],
            'issuer_vat_country' => ['required', 'alpha', 'size:2'],
            'issuer_vat_number' => ['nullable', 'string', 'max:28'],
            'issuer_fiscal_code' => ['nullable', 'string', 'max:16'],
            'issuer_tax_regime' => ['required', 'regex:/^RF\d{2}$/'],
            'issuer_address' => ['nullable', 'string', 'max:60'],
            'issuer_postal_code' => ['nullable', 'string', 'max:10'],
            'issuer_city' => ['nullable', 'string', 'max:60'],
            'issuer_province' => ['nullable', 'alpha', 'size:2'],
            'issuer_country' => ['required', 'alpha', 'size:2'],
            'invoice_prefix' => ['required', 'regex:/^[A-Za-z0-9_-]{1,12}$/'],
            'next_invoice_number' => ['required', 'integer', 'min:1'],
            'default_vat_rate' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'prices_include_vat' => ['required', 'boolean'],
            'ticket_format' => ['required', Rule::in(['a4', 'a6', 'label', 'custom'])],
            'ticket_orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'ticket_width_mm' => ['required', 'integer', 'min:30', 'max:216'],
            'ticket_height_mm' => ['required', 'integer', 'min:30', 'max:356'],
            'ticket_auto_open_on_exit' => ['required', 'boolean'],
            'ticket_show_logo' => ['required', 'boolean'],
            'ticket_title' => ['required', 'string', 'max:80'],
            'ticket_footer' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['invoice_mode'] !== 'simulator') {
            $required = [
                'issuer_business_name' => __('Ragione sociale emittente'),
                'issuer_vat_number' => __('Partita IVA emittente'),
                'issuer_address' => __('Indirizzo emittente'),
                'issuer_postal_code' => __('CAP emittente'),
                'issuer_city' => __('Comune emittente'),
            ];

            foreach ($required as $field => $label) {
                if (blank($validated[$field] ?? null)) {
                    return back()->withInput()->withErrors([$field => __('Il campo :field è obbligatorio per Aruba.', ['field' => $label])]);
                }
            }

            if (blank($validated['aruba_username'] ?? null) && blank($settings->aruba_username)) {
                return back()->withInput()->withErrors(['aruba_username' => __('Inserisci lo username Aruba.')]);
            }

            if (blank($validated['aruba_password'] ?? null) && blank($settings->aruba_password)) {
                return back()->withInput()->withErrors(['aruba_password' => __('Inserisci la password Aruba.')]);
            }
        }

        foreach (['issuer_vat_country', 'issuer_fiscal_code', 'issuer_tax_regime', 'issuer_province', 'issuer_country', 'invoice_prefix'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = strtoupper(trim((string) $validated[$field]));
            }
        }

        if (blank($validated['aruba_username'] ?? null)) {
            unset($validated['aruba_username']);
        }
        if (blank($validated['aruba_password'] ?? null)) {
            unset($validated['aruba_password']);
        }

        foreach (['ticket_title', 'ticket_footer'] as $field) {
            $storedValue = $settings->{$field};
            if (filled($storedValue) && ($validated[$field] ?? null) === __($storedValue)) {
                $validated[$field] = $storedValue;
            }
        }

        $settings->update($validated);

        return redirect()
            ->route('operational-settings.edit', ['parking_id' => $parking->id])
            ->with('success', __('Impostazioni salvate.'));
    }
}
