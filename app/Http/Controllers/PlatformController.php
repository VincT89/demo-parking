<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\ParkingListing;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function index()
    {
        $platforms = Platform::with(['listings.parking'])
            ->withCount('listings')
            ->orderBy('name')
            ->get();

        $lastSyncLog = \App\Models\SyncLog::with(['platform', 'parkingListing'])
            ->latest()
            ->first();

        return view('platforms.index', compact('platforms', 'lastSyncLog'));
    }

    public function create()
    {
        return view('platforms.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('platforms', 'slug')->where('management_id', app(\App\Tenancy\ParkingContext::class)->management->id)],
            'website'       => ['nullable', 'url', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active'     => ['boolean'],
        ]);

        Platform::create([
            'name'          => $validated['name'],
            'slug'          => $validated['slug'],
            'website'       => $validated['website'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'is_active'     => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('platforms.index')
            ->with('success', __('Piattaforma commerciale creata con successo.'));
    }

    public function edit(Platform $platform)
    {
        $platform->load(['listings.parking.products' => function ($q) {
            $q->active();
        }]);
        
        $alreadyAttachedIds = $platform->listings->pluck('parking_id')->toArray();
        $parkings = \App\Models\Parking::active()
                        ->whereNotIn('id', $alreadyAttachedIds)
                        ->get();
        
        $mappings = \App\Models\PlatformProductMapping::where('platform_id', $platform->id)->get();
                        
        return view('platforms.edit', compact('platform', 'parkings', 'mappings'));
    }

    public function update(Request $request, Platform $platform)
    {
        if ($platform->uses_legacy_credentials && $request->input('slug') !== $platform->slug) {
            \Illuminate\Support\Facades\Gate::authorize('manage-shared-connections');
        }
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('platforms', 'slug')->where('management_id', $platform->management_id)->ignore($platform->id)],
            'website'       => ['nullable', 'url', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            // is_active omesso dal validate volutamente
        ]);

        $platform->update([
            'name'          => $validated['name'],
            'slug'          => $validated['slug'],
            'website'       => $validated['website'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'is_active'     => $request->boolean('is_active', false),
        ]);

        return redirect()
            ->route('platforms.index')
            ->with('success', __('Configurazione piattaforma aggiornata.'));
    }

    public function attachToParking(Request $request, Platform $platform)
    {
        $validated = $request->validate([
            'parking_id' => ['required', \Illuminate\Validation\Rule::exists('parkings', 'id')->where('id', app(\App\Tenancy\ParkingContext::class)->parking?->id ?? 0)]
        ]);

        $listing = ParkingListing::firstOrNew([
            'parking_id'  => $validated['parking_id'],
            'platform_id' => $platform->id,
        ]);

        $listing->platform_id = $platform->id;
        $listing->is_active = true;
        $listing->save();

        return redirect()
            ->route('platforms.edit', $platform)
            ->with('success', __('Canale collegato al parcheggio e abilitato alla vendita.'));
    }

    public function destroy(Platform $platform)
    {
        $platform->update(['is_active' => false]);

        return redirect()
            ->route('platforms.index')
            ->with('success', __('Piattaforma disattivata.'));
    }

    public function storeMapping(Request $request, Platform $platform)
    {
        $validated = $request->validate([
            'parking_product_id' => ['required', \Illuminate\Validation\Rule::exists('parking_products', 'id')->where('parking_id', app(\App\Tenancy\ParkingContext::class)->parking?->id ?? 0)],
            'external_ref'       => ['required', 'string', 'max:255'],
            'external_name'      => ['nullable', 'string', 'max:255'],
        ]);

        // Check for an existing active mapping with the same external_ref
        $existing = \App\Models\PlatformProductMapping::where('platform_id', $platform->id)
            ->where('external_ref', $validated['external_ref'])
            ->active()
            ->first();

        if ($existing) {
            return back()->withErrors(['external_ref' => __('Esiste già un mapping attivo per questo codice esterno.')]);
        }

        \App\Models\PlatformProductMapping::create([
            'platform_id'        => $platform->id,
            'parking_product_id' => $validated['parking_product_id'],
            'external_ref'       => $validated['external_ref'],
            'external_name'      => $validated['external_name'] ?? null,
            'is_active'          => true,
        ]);

        return back()->with('success', __('Mapping prodotto aggiunto con successo.'));
    }

    public function destroyMapping(\App\Models\PlatformProductMapping $mapping)
    {
        $mapping->update(['is_active' => false]);
        
        return back()->with('success', __('Mapping rimosso con successo.'));
    }
}
