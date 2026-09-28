<?php

namespace App\Http\Controllers;

use App\Models\ParkingListing;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PlatformConnectionController extends Controller
{
    public function update(Request $request, Platform $platform)
    {
        if ($request->boolean('use_shared_connection')) {
            Gate::authorize('manage-shared-connections');
            abort_unless(in_array($platform->slug, ['parkos', 'parking-my-car', 'vologio']), 422);
            $platform->uses_legacy_credentials = true;
            $platform->connection_settings = null;
            $platform->save();

            return back()->with('success', __('Connessione esistente abilitata. Verifica l’identificativo del nuovo parcheggio prima di sincronizzare.'));
        }
        abort_if($platform->uses_legacy_credentials, 403, __('La connessione condivisa è gestita separatamente.'));
        $fields = match ($platform->slug) {
            'parkos', 'parking-my-car' => ['client_id', 'client_secret', 'username', 'password'],
            'vologio' => ['api_key', 'client_id'],
            default => abort(422, __('Questo canale non dispone di una connessione API.')),
        };
        $rules = [];
        foreach ($fields as $field) {
            $rules['credentials.'.$field] = ['nullable', 'string', 'max:2000'];
        }
        $validated = $request->validate($rules);
        $credentials = $platform->connection_settings ?? [];
        foreach ($validated['credentials'] ?? [] as $key => $value) {
            if (filled($value)) {
                $credentials[$key] = $value;
            }
        }
        foreach ($fields as $field) {
            if (blank($credentials[$field] ?? null)) {
                return back()->withErrors(['credentials.'.$field => __('Completa tutti i campi della connessione.')]);
            }
        }
        $platform->connection_settings = $credentials;
        $platform->save();

        return back()->with('success', __('Credenziali salvate per questa gestione.'));
    }

    public function updateListing(Request $request, Platform $platform, ParkingListing $listing)
    {
        abort_unless($listing->platform_id === $platform->id, 404);
        if ($platform->uses_legacy_credentials) {
            Gate::authorize('manage-shared-connections');
        }
        $data = $request->validate(['external_id' => ['nullable', 'string', 'max:255'], 'is_active' => ['boolean']]);
        $listing->update(['external_id' => $data['external_id'] ?? null, 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Collegamento del parcheggio aggiornato.'));
    }
}
