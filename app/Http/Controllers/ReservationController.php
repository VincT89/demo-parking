<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ParkingListing;
use App\Models\Platform;
use App\Services\ReservationService;
use App\Services\AvailabilityService;
use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use App\Exports\ReservationsExport;
use Maatwebsite\Excel\Facades\Excel;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $reservationService,
        private AvailabilityService $availabilityService,
    ) {}

    public function index(Request $request)
    {
        if ($request->get('quick_filter') === 'arrivals_today') {
            $request->merge([
                'date_filter_type' => 'starts_at',
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]);
        }

        if ($request->get('quick_filter') === 'departures_today') {
            $request->merge([
                'date_filter_type' => 'ends_at',
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]);
        }

        $query = Reservation::query()
            ->with(['parkingListing.platform', 'parkingListing.parking', 'latestPayment']);

        match ($request->sort_by) {
            'starts_at_asc'   => $query->orderBy('starts_at', 'asc'),
            'starts_at_desc'  => $query->orderBy('starts_at', 'desc'),
            'created_at_asc'  => $query->orderBy('created_at', 'asc'),
            default           => $query->orderBy('created_at', 'desc'), // Default: dalle più recenti inserite
        };

        // Filtro per piattaforma
        if ($request->filled('platform_id')) {
            $query->whereHas('parkingListing', function ($q) use ($request) {
                $q->where('platform_id', $request->platform_id);
            });
        }

        // Filtro per stato
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro per data
        $dateColumn = $request->input('date_filter_type', 'starts_at');

        if (! in_array($dateColumn, ['starts_at', 'ends_at'], true)) {
            $dateColumn = 'starts_at';
        }

        if ($request->filled('date_from')) {
            $query->whereDate($dateColumn, '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate($dateColumn, '<=', $request->date_to);
        }

        // Filtro di ricerca globale (nome in AND, altri campi in OR)
        if ($request->filled('search')) {
            $search = trim(preg_replace('/\s+/', ' ', $request->search));
            $terms = collect(explode(' ', $search))->filter()->values();

            $query->where(function ($q) use ($search, $terms) {
                // AND sui termini del nome
                $q->where(function ($nameQuery) use ($terms) {
                    foreach ($terms as $term) {
                        $nameQuery->where('customer_name', 'like', '%' . $term . '%');
                    }
                })
                // OR sugli altri campi esatti
                ->orWhere('customer_email', 'like', '%' . $search . '%')
                ->orWhere('customer_phone', 'like', '%' . $search . '%')
                ->orWhere('license_plate', 'like', '%' . $search . '%')
                ->orWhere('flight_reference', 'like', '%' . $search . '%')
                ->orWhere('external_id', 'like', '%' . $search . '%');
            });
        }

        $reservations = $query->paginate(20)->withQueryString();
        $platforms    = Platform::active()->get();
        $statuses     = ReservationStatus::cases();

        return view('reservations.index', compact(
            'reservations',
            'platforms',
            'statuses'
        ));
    }

    public function create()
    {
        $listings  = ParkingListing::with('platform')
            ->active()
            ->get();
        $statuses  = ReservationStatus::cases();
        $products  = \App\Models\ParkingProduct::active()
            ->get();

        return view('reservations.create', compact('listings', 'statuses', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parking_listing_id' => ['required', 'exists:parking_listings,id'],
            'parking_product_id' => ['required', 'exists:parking_products,id'],
            'customer_name'      => ['required', 'string', 'max:255'],
            'customer_email'     => ['nullable', 'email', 'max:255'],
            'customer_phone'     => ['nullable', 'string', 'max:50'],
            'license_plate'      => ['nullable', 'string', 'max:20'],
            'flight_reference'   => ['nullable', 'string', 'max:20'],
            'starts_at'          => ['required', 'date', 'before:ends_at'],
            'ends_at'            => ['required', 'date', 'after:starts_at'],
            'spots'              => ['required', 'integer', 'min:1'],
            'passengers_count'   => ['required', 'integer', 'min:1', 'max:9'],
            'price'              => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string'],
        ]);

        if (!empty($validated['flight_reference'])) {
            $validated['flight_reference'] = strtoupper(trim($validated['flight_reference']));
        }

        $listing = ParkingListing::findOrFail($validated['parking_listing_id']);
        $result  = $this->reservationService->create($listing, $validated);

        if (! $result->success) {
            return back()
                ->withInput()
                ->withErrors(['availability' => $result->error]);
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Prenotazione creata con successo.');
    }

    public function show(Reservation $reservation)
    {
        $reservation->load(['parkingListing.platform', 'parkingListing.parking', 'parking.settings', 'parkingProduct', 'latestPayment', 'electronicInvoice']);
        return view('reservations.show', compact('reservation'));
    }

    public function edit(Reservation $reservation)
    {
        $listings = ParkingListing::with('platform')
            ->active()
            ->get();
        $statuses = ReservationStatus::cases();
        $products = \App\Models\ParkingProduct::active()
            ->get();

        return view('reservations.edit', compact(
            'reservation',
            'listings',
            'statuses',
            'products'
        ));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'parking_product_id' => ['required', 'exists:parking_products,id'],
            'customer_name'  => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'license_plate'  => ['nullable', 'string', 'max:20'],
            'flight_reference' => ['nullable', 'string', 'max:20'],
            'starts_at'      => ['required', 'date', 'before:ends_at'],
            'ends_at'        => ['required', 'date', 'after:starts_at'],
            'spots'          => ['required', 'integer', 'min:1'],
            'passengers_count' => ['required', 'integer', 'min:1', 'max:9'],
            'status'         => ['required', 'string'],
            'price'          => ['nullable', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        if (!empty($validated['flight_reference'])) {
            $validated['flight_reference'] = strtoupper(trim($validated['flight_reference']));
        }

        $result = $this->reservationService->update($reservation, $validated);

        if (! $result->success) {
            return back()
                ->withInput()
                ->withErrors(['availability' => $result->error]);
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Prenotazione aggiornata con successo.');
    }

    public function destroy(Reservation $reservation)
    {
        $result = $this->reservationService->cancel($reservation);

        if (! $result->success) {
            return back()->withErrors(['error' => $result->error]);
        }

        return redirect()
            ->route('reservations.index')
            ->with('success', 'Prenotazione cancellata con successo.');
    }

    public function export(Request $request)
    {
        $filename = 'prenotazioni_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new ReservationsExport(
                platformId: $request->input('platform_id'),
                status:     $request->input('status'),
                dateFrom:   $request->input('date_from'),
                dateTo:     $request->input('date_to'),
                dateFilterType: $request->input('date_filter_type', 'starts_at'),
            ),
            $filename
        );
    }

    public function toggleMovement(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:entered,exited'],
            'value' => ['required', 'boolean'],
        ]);

        if ($validated['type'] === 'entered') {
            $reservation->update(['has_entered' => $validated['value']]);
        } elseif ($validated['type'] === 'exited') {
            $reservation->update(['has_exited' => $validated['value']]);
        }

        $printUrl = null;

        if ($validated['type'] === 'exited' && $validated['value']) {
            $settings = \App\Models\ParkingSetting::query()->firstOrCreate([
                'parking_id' => $reservation->parking_id,
            ]);

            if ($settings->ticket_auto_open_on_exit) {
                $printUrl = route('reservations.ticket', [
                    'reservation' => $reservation,
                    'autoprint' => 1,
                ]);
            }
        }

        return response()->json(['success' => true, 'print_url' => $printUrl]);
    }

    public function markPaid(Reservation $reservation)
    {
        if ($reservation->parkingListing?->platform?->slug !== 'website') {
            return back()->with('error', 'Pagamento manuale disponibile solo per prenotazioni dal sito.');
        }

        $payment = $reservation->latestPayment;

        if (! $payment) {
            $payment = $reservation->payments()->create([
                'provider' => 'onsite',
                'status' => PaymentStatus::Pending->value,
                'amount' => $reservation->price,
                'currency' => 'EUR',
                'raw_data' => [
                    'source' => 'admin_manual_payment',
                ],
            ]);
        }

        $payment->update([
            'status' => PaymentStatus::Paid->value,
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Pagamento segnato come pagato.');
    }
}
