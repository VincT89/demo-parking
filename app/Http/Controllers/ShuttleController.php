<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\ShuttleDirection;
use App\Enums\ShuttleTripStatus;
use App\Models\Parking;
use App\Models\Reservation;
use App\Models\ShuttleSetting;
use App\Models\ShuttleTrip;
use App\Models\ShuttleTripAssignment;
use App\Models\ShuttleVehicle;
use App\Services\ShuttlePlannerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class ShuttleController extends Controller
{
    public function __construct(private ShuttlePlannerService $planner) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'parking_id' => ['nullable', 'exists:parkings,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $parkings = Parking::query()->active()->orderBy('name')->get();
        abort_if($parkings->isEmpty(), 404, __('Nessun parcheggio attivo configurato nel sistema.'));

        $parking = $parkings->firstWhere('id', (int) ($validated['parking_id'] ?? 0)) ?? $parkings->first();
        $date = Carbon::createFromFormat('Y-m-d', $validated['date'] ?? now()->toDateString())->startOfDay();
        $settings = ShuttleSetting::query()->where('parking_id', $parking->id)->first();
        $vehicles = ShuttleVehicle::query()
            ->where('parking_id', $parking->id)
            ->active()
            ->orderBy('name')
            ->get();

        $trips = ShuttleTrip::query()
            ->where('parking_id', $parking->id)
            ->whereDate('scheduled_at', $date->toDateString())
            ->with(['vehicle', 'assignments.reservation'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        $reservations = Reservation::query()
            ->where('parking_id', $parking->id)
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Modified->value,
            ])
            ->where(function ($query) use ($date): void {
                $query
                    ->whereDate('starts_at', $date->toDateString())
                    ->orWhereDate('ends_at', $date->toDateString());
            })
            ->orderBy('starts_at')
            ->get();

        $unassigned = collect();
        foreach ($reservations as $reservation) {
            if ($reservation->starts_at->isSameDay($date)) {
                $remaining = $this->planner->remainingPassengers($reservation, ShuttleDirection::Outbound);
                if ($remaining > 0) {
                    $unassigned->push([
                        'reservation' => $reservation,
                        'direction' => ShuttleDirection::Outbound,
                        'remaining' => $remaining,
                        'time' => $reservation->starts_at,
                    ]);
                }
            }

            if ($reservation->ends_at->isSameDay($date)) {
                $remaining = $this->planner->remainingPassengers($reservation, ShuttleDirection::Return);
                if ($remaining > 0) {
                    $unassigned->push([
                        'reservation' => $reservation,
                        'direction' => ShuttleDirection::Return,
                        'remaining' => $remaining,
                        'time' => $reservation->ends_at,
                    ]);
                }
            }
        }

        return view('shuttles.index', [
            'parkings' => $parkings,
            'parking' => $parking,
            'date' => $date,
            'settings' => $settings,
            'vehicles' => $vehicles,
            'trips' => $trips,
            'unassigned' => $unassigned->sortBy('time')->values(),
            'directions' => ShuttleDirection::cases(),
            'statuses' => ShuttleTripStatus::cases(),
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'parking_id' => ['required', 'exists:parkings,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $created = $this->planner->generate(
                Parking::query()->findOrFail($validated['parking_id']),
                Carbon::createFromFormat('Y-m-d', $validated['date']),
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['shuttle' => $exception->getMessage()]);
        }

        return back()->with('success', trans_choice(
            '{0} Nessun nuovo viaggio necessario.|{1} Creato :count nuovo viaggio proposto.|[2,*] Creati :count nuovi viaggi proposti.',
            $created,
            ['count' => $created],
        ));
    }

    public function storeTrip(Request $request)
    {
        $validated = $this->tripData($request);
        $vehicle = filled($validated['shuttle_vehicle_id'] ?? null)
            ? ShuttleVehicle::query()->findOrFail($validated['shuttle_vehicle_id'])
            : null;

        if ($vehicle && (int) $vehicle->parking_id !== (int) $validated['parking_id']) {
            return back()->withErrors(['shuttle_vehicle_id' => __('La navetta appartiene a un altro parcheggio.')]);
        }

        ShuttleTrip::query()->create([
            ...$validated,
            'capacity' => $vehicle?->seats ?? $validated['capacity'],
            'is_generated' => false,
        ]);

        return back()->with('success', __('Viaggio navetta creato.'));
    }

    public function updateTrip(Request $request, ShuttleTrip $trip)
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'shuttle_vehicle_id' => ['nullable', 'exists:shuttle_vehicles,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::enum(ShuttleTripStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $vehicle = filled($validated['shuttle_vehicle_id'] ?? null)
            ? ShuttleVehicle::query()->findOrFail($validated['shuttle_vehicle_id'])
            : null;

        if ($vehicle && (int) $vehicle->parking_id !== (int) $trip->parking_id) {
            return back()->withErrors(['shuttle_vehicle_id' => __('La navetta appartiene a un altro parcheggio.')]);
        }

        $assigned = $trip->assignments()->sum('passengers');
        $capacity = $vehicle?->seats ?? (int) $validated['capacity'];

        if ($capacity < $assigned) {
            return back()->withErrors(['capacity' => __('La capienza non può essere inferiore ai passeggeri già assegnati.')]);
        }

        $trip->update([
            ...$validated,
            'capacity' => $capacity,
        ]);

        return back()->with('success', __('Viaggio navetta aggiornato.'));
    }

    public function cancelTrip(ShuttleTrip $trip)
    {
        $trip->update(['status' => ShuttleTripStatus::Cancelled->value]);

        return back()->with('success', __('Viaggio navetta annullato. Le prenotazioni sono nuovamente assegnabili.'));
    }

    public function assign(Request $request, ShuttleTrip $trip)
    {
        $validated = $request->validate([
            'reservation_id' => ['required', 'exists:reservations,id'],
            'passengers' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $this->planner->assign(
                $trip,
                Reservation::query()->findOrFail($validated['reservation_id']),
                (int) $validated['passengers'],
            );
        } catch (LogicException $exception) {
            return back()->withErrors(['assignment' => $exception->getMessage()]);
        }

        return back()->with('success', __('Gruppo assegnato al viaggio.'));
    }

    public function unassign(ShuttleTripAssignment $assignment)
    {
        $assignment->delete();

        return back()->with('success', __('Gruppo rimosso dal viaggio.'));
    }

    private function tripData(Request $request): array
    {
        return $request->validate([
            'parking_id' => ['required', 'exists:parkings,id'],
            'direction' => ['required', Rule::enum(ShuttleDirection::class)],
            'scheduled_at' => ['required', 'date'],
            'shuttle_vehicle_id' => ['nullable', 'exists:shuttle_vehicles,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::enum(ShuttleTripStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
