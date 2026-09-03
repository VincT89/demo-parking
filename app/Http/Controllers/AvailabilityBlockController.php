<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityBlock;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Enums\BlockType;
use Illuminate\Http\Request;

class AvailabilityBlockController extends Controller
{
    public function index()
    {
        $blocks = AvailabilityBlock::query()
            ->with(['parking', 'parkingListing.platform', 'createdBy'])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('availability-blocks.index', compact('blocks'));
    }

    public function create()
    {
        $parkings   = Parking::active()->get();
        $listings   = ParkingListing::with('platform')
            ->active()
            ->get();
        $blockTypes = BlockType::cases();

        return view('availability-blocks.create', compact(
            'parkings',
            'listings',
            'blockTypes'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parking_id'         => ['required', 'exists:parkings,id'],
            'parking_listing_id' => ['nullable', 'exists:parking_listings,id'],
            'type'               => ['required', 'string'],
            'starts_at'          => ['required', 'date', 'before:ends_at'],
            'ends_at'            => ['required', 'date', 'after:starts_at'],
            'spots'              => ['required', 'integer', 'min:1'],
            'reason'             => ['nullable', 'string', 'max:500'],
        ]);

        // Forza null se non presente
        $validated['parking_listing_id'] = $request->filled('parking_listing_id')
            ? $validated['parking_listing_id']
            : null;

        $parking = Parking::findOrFail($validated['parking_id']);
        if ($parking->capacity_mode === 'per_product') {
            abort(422, 'I blocchi globali non sono ammessi per i parcheggi in modalità Per Product.');
        }

        $validated['created_by'] = auth()->id();

        AvailabilityBlock::create($validated);

        return redirect()
            ->route('availability-blocks.index')
            ->with('success', 'Blocco creato con successo.');
    }

    public function destroy(AvailabilityBlock $availabilityBlock)
    {
        $availabilityBlock->update(['is_active' => false]);

        return redirect()
            ->route('availability-blocks.index')
            ->with('success', 'Blocco eliminato con successo.');
    }
}