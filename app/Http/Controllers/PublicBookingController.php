<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Parking;
use App\Models\ParkingProduct;
use App\Models\ParkingListing;
use App\Models\Platform;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use App\Services\ParkingAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Enums\ReservationStatus;

class PublicBookingController extends Controller
{
    public function showForm(Request $request)
    {
        // Raccogliamo i codici prodotto univoci da tutti i parcheggi attivi
        // Così mostriamo solo le tipologie logiche (es. 'standard', 'premium')
        $products = ParkingProduct::whereHas('parking', function ($q) {
            $q->active();
        })->active()
          ->get()
          ->unique('code');

        if ($products->isEmpty()) {
            abort(404, __('Nessun prodotto disponibile per la prenotazione.'));
        }

        return view('booking.form', compact('products'));
    }

    public function checkAvailability(Request $request, ParkingAssignmentService $assignmentService)
    {
        $validated = $request->validate([
            'product_code' => 'required|string|exists:parking_products,code',
            'arrival_date' => ['required', 'date_format:Y-m-d'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'departure_time' => ['required', 'date_format:H:i'],
            'spots' => 'integer|min:1|max:10',
        ]);

        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['arrival_date'].' '.$validated['arrival_time']
        );

        $endsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['departure_date'].' '.$validated['departure_time']
        );

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return response()->json([
                'available' => false,
                'reason' => __('La partenza deve essere successiva all’arrivo.')
            ]);
        }

        $spots = $validated['spots'] ?? 1;

        try {
            $result = $assignmentService->findFirstAvailable(
                $validated['product_code'],
                $startsAt,
                $endsAt,
                $spots
            );

            return response()->json([
                'available' => true,
                'total_price' => $result['price'],
                'price_formatted' => number_format($result['price'], 2, ',', '.') . ' €'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'reason' => $e->getMessage()
            ]);
        }
    }

    public function store(Request $request, ReservationService $reservationService, ParkingAssignmentService $assignmentService)
    {
        $validated = $request->validate([
            'product_code' => 'required|string|exists:parking_products,code',
            'arrival_date' => ['required', 'date_format:Y-m-d'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'departure_time' => ['required', 'date_format:H:i'],
            'spots' => 'required|integer|min:1|max:10',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:50',
            'license_plate' => 'required|string|max:20',
            'flight_reference' => ['nullable', 'string', 'max:20'],
            'passengers_count' => ['required', 'integer', 'min:1', 'max:9'],
        ]);

        // Website Platform (deve già esistere, configurata via seeder, altrimenti fallisce esplicitamente 404)
        $platform = Platform::where('slug', 'website')->firstOrFail();

        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['arrival_date'].' '.$validated['arrival_time']
        );

        $endsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['departure_date'].' '.$validated['departure_time']
        );

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return back()
                ->withErrors(['departure_date' => __('La partenza deve essere successiva all’arrivo.')])
                ->withInput();
        }

        try {
            // Rifà l'assegnazione autoritativa
            $assignment = $assignmentService->findFirstAvailable(
                $validated['product_code'],
                $startsAt,
                $endsAt,
                $validated['spots']
            );
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Recupera il listing per questa piattaforma e il parcheggio assegnato (fail fast se non esiste)
        $listing = ParkingListing::where('parking_id', $assignment['parking']->id)
            ->where('platform_id', $platform->id)
            ->active()
            ->firstOrFail();

        $data = [
            'parking_product_id' => $assignment['product']->id,
            'external_id' => 'WEB-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'license_plate' => $validated['license_plate'],
            'flight_reference' => filled($validated['flight_reference'] ?? null)
                ? strtoupper(trim($validated['flight_reference']))
                : null,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'spots' => $validated['spots'],
            'price' => $assignment['price'],
            'status' => ReservationStatus::Pending->value, // Stato iniziale
            'expires_at' => now()->addMinutes(15),
            'passengers_count' => max(1, (int) $validated['passengers_count']),
            'raw_data' => [
                'source' => 'website',
                'demo' => (bool) config('demo.enabled'),
            ]
        ];

        $result = $reservationService->create($listing, $data);

        if (!$result->success) {
            return back()->withInput()->with('error', $result->error);
        }

        return redirect()->route('public.booking.payment', $result->reservation->external_id);
    }

    public function success($code)
    {
        $reservation = Reservation::where('external_id', $code)->firstOrFail();
        abort_if($reservation->status !== ReservationStatus::Confirmed, 404);
        
        return view('booking.success', compact('reservation'));
    }
}
