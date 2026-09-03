<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Http\Requests\StoreParkingRequest;
use App\Http\Requests\UpdateParkingRequest;

class ParkingController extends Controller
{
    /**
     * Display a listing of the parkings.
     */
    public function index()
    {
        // Admin vede tutto: attivi e disattivati
        $parkings = Parking::orderBy('name')->get();
        return view('parkings.index', compact('parkings'));
    }

    /**
     * Show the form for creating a new parking.
     */
    public function create()
    {
        return view('parkings.create');
    }

    /**
     * Store a newly created parking in storage.
     */
    public function store(StoreParkingRequest $request)
    {
        Parking::create($request->validated());

        return redirect()->route('parkings.index')->with('success', 'Parcheggio creato correttamente.');
    }

    /**
     * Show the form for editing the specified parking.
     */
    public function edit(Parking $parking)
    {
        $parking->load([
            'products' => function($q) {
                $q->orderBy('sort_order', 'asc');
            },
            'allocations' => function($q) {
                $q->orderBy('starts_at', 'desc');
            }
        ]);

        // Per mantenere la vecchia logica del dropdown nel layout / navigazione (se necessario)
        $parkings = Parking::orderBy('name')->get();

        return view('parkings.edit', compact('parking', 'parkings'));
    }

    /**
     * Update the specified parking in storage.
     */
    public function update(UpdateParkingRequest $request, Parking $parking)
    {
        $parking->update($request->validated());

        // Trigger Cache clear 
        if (auth()->check()) {
            cache()->forget('alert_count_' . auth()->id());
        }

        return redirect()->route('parkings.edit', $parking)->with('success', 'Configurazione parcheggio aggiornata.');
    }

    /**
     * Deactivate the specified parking (Soft Delete).
     */
    public function destroy(Parking $parking)
    {
        $parking->update(['is_active' => false]);
        
        // Trigger Cache clear
        if (auth()->check()) {
            cache()->forget('alert_count_' . auth()->id());
        }

        return redirect()->route('parkings.index')->with('success', 'Parcheggio disattivato con successo.');
    }
}
