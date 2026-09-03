<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $parkings = Parking::active()->orderBy('id')->get();
        if ($parkings->isEmpty()) {
            abort(404, 'Nessun parcheggio attivo configurato nel sistema.');
        }

        $parkingId = $request->input('parking_id');
        $parking = $parkingId ? $parkings->firstWhere('id', $parkingId) : $parkings->first();
        
        if (!$parking) {
            $parking = $parkings->first();
        }

        $totalSpots = $parking->getComputedTotalSpots();
        $platforms = \App\Models\Platform::active()->get();
        $products = \App\Models\ParkingProduct::where('parking_id', $parking->id)->active()->orderBy('sort_order')->get();
        
        return view('calendar.index', compact('parking', 'parkings', 'platforms', 'products', 'totalSpots'));
    }

    public function data(Request $request)
    {
        $parkings = Parking::active()->orderBy('id')->get();
        if ($parkings->isEmpty()) {
            return response()->json(['reservations' => [], 'from' => null, 'to' => null, 'days' => 0]);
        }

        $parkingId = $request->input('parking_id');
        $parking = $parkingId ? $parkings->firstWhere('id', $parkingId) : $parkings->first();
        
        if (!$parking) {
            $parking = $parkings->first();
        }

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $to = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $reservations = Reservation::query()
            ->where('parking_id', $parking->id)
            ->with(['parkingListing.platform', 'parkingProduct'])
            ->active()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('starts_at', [$from, $to])
                    ->orWhereBetween('ends_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('starts_at', '<=', $from)
                            ->where('ends_at', '>=', $to);
                    });
            })
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'customer_name' => $r->customer_name,
                'license_plate' => $r->license_plate,
                'flight_reference' => $r->flight_reference,
                'platform' => $r->parkingListing?->platform?->name ?? 'Unknown',
                'platform_slug' => $r->parkingListing?->platform?->slug ?? 'unknown',
                'product_name' => $r->parkingProduct?->name ?? 'Senza Categoria',
                'product_code' => $r->parkingProduct?->code ?? 'unknown',
                'starts_at' => $r->starts_at->format('Y-m-d'),
                'ends_at' => $r->ends_at->format('Y-m-d'),
                'starts_at_time' => $r->starts_at->format('H:i'),
                'ends_at_time' => $r->ends_at->format('H:i'),
                'spots' => $r->spots,
                'status' => $r->status->value,
                'price' => $r->price,
            ]);

        return response()->json([
            'reservations' => $reservations,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'days' => $from->daysInMonth,
        ]);
    }

    public function day(Request $request)
    {
        $type = $request->get('type', 'entries');
        $date = Carbon::parse(
            $request->get('date', now(config('app.timezone'))->toDateString()),
            config('app.timezone')
        );
        $parkingId = $request->get('parking_id');

        abort_unless(in_array($type, ['entries', 'exits']), 404);

        $reservations = $this->getDayReservationsQuery($type, $date, $parkingId)->get();
        $reservationsCount = $reservations->count();

        return view('calendar.day', compact('reservations', 'reservationsCount', 'type', 'date', 'parkingId'));
    }

    public function exportDay(Request $request)
    {
        $type = $request->get('type', 'entries');
        $date = Carbon::parse(
            $request->get('date', now(config('app.timezone'))->toDateString()),
            config('app.timezone')
        );
        $parkingId = $request->get('parking_id');

        abort_unless(in_array($type, ['entries', 'exits']), 404);

        $reservations = $this->getDayReservationsQuery($type, $date, $parkingId)->get();
        
        $typeName = $type === 'entries' ? 'entrate' : 'uscite';
        $filename = "{$typeName}-{$date->format('Y-m-d')}.csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Ora', 'Targa', 'Volo', 'Cliente', 'Telefono', 'Clienti', 'Prodotto', 'Parcheggio', 'Posti/Macchine', 'Note'];

        $callback = function() use($reservations, $columns, $type) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns, ';');

            foreach ($reservations as $res) {
                $time = $type === 'entries' ? $res->starts_at->format('H:i') : $res->ends_at->format('H:i');
                
                $note = $res->notes;
                if ($note && str_contains(strtolower($note), 'demo')) {
                    $note = null;
                }

                fputcsv($file, [
                    $time,
                    $res->license_plate ?? '-',
                    $res->flight_reference ?? '-',
                    $res->customer_name,
                    $res->customer_phone ?? '-',
                    $res->passengers_count ?? 1,
                    $res->parkingProduct->name ?? 'N/D',
                    $res->parking->name ?? 'N/D',
                    $res->spots,
                    $note ?? '-'
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getDayReservationsQuery(string $type, Carbon $date, ?int $parkingId)
    {
        $query = Reservation::query()
            ->with(['parking', 'parkingProduct'])
            ->active();

        if ($parkingId) {
            $query->where('parking_id', $parkingId);
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        if ($type === 'entries') {
            $query->whereBetween('starts_at', [$start, $end])
                  ->orderBy('starts_at');
        } elseif ($type === 'exits') {
            $query->whereBetween('ends_at', [$start, $end])
                  ->orderBy('ends_at');
        }

        return $query;
    }
}
