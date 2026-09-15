<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\ShuttleDirection;
use App\Enums\ShuttleTripStatus;
use App\Models\Parking;
use App\Models\Reservation;
use App\Models\ShuttleSetting;
use App\Models\ShuttleTrip;
use App\Models\ShuttleVehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ShuttlePlannerService
{
    public function generate(Parking $parking, Carbon $date): int
    {
        return DB::transaction(function () use ($parking, $date): int {
            $settings = ShuttleSetting::query()->firstOrCreate(['parking_id' => $parking->id]);

            if (! $settings->is_enabled) {
                throw new LogicException(__('La gestione navetta non è attiva per questo parcheggio.'));
            }

            $vehicles = ShuttleVehicle::query()
                ->where('parking_id', $parking->id)
                ->active()
                ->orderByDesc('seats')
                ->lockForUpdate()
                ->get();

            $created = 0;

            foreach (ShuttleDirection::cases() as $direction) {
                $column = $direction === ShuttleDirection::Outbound ? 'starts_at' : 'ends_at';
                $offset = $direction === ShuttleDirection::Outbound
                    ? $settings->outbound_offset_minutes
                    : $settings->return_offset_minutes;

                $reservations = Reservation::query()
                    ->where('parking_id', $parking->id)
                    ->whereIn('status', [
                        ReservationStatus::Confirmed->value,
                        ReservationStatus::Modified->value,
                    ])
                    ->whereDate($column, $date->toDateString())
                    ->where(fn ($query) => $query
                        ->whereNull('passengers_count')
                        ->orWhere('passengers_count', '>', 0))
                    ->orderBy($column)
                    ->lockForUpdate()
                    ->get();

                foreach ($reservations as $reservation) {
                    $remaining = $this->remainingPassengers($reservation, $direction);
                    $desiredAt = $reservation->{$column}->copy()->addMinutes($offset);

                    while ($remaining > 0) {
                        $trip = $this->matchingTrip(
                            $parking,
                            $direction,
                            $desiredAt,
                            (int) $settings->grouping_window_minutes,
                        );

                        if (! $trip) {
                            $vehicle = $this->availableVehicle(
                                $vehicles,
                                $desiredAt,
                                (int) $settings->turnaround_minutes,
                            );

                            $trip = ShuttleTrip::query()->create([
                                'parking_id' => $parking->id,
                                'shuttle_vehicle_id' => $vehicle?->id,
                                'direction' => $direction->value,
                                'scheduled_at' => $desiredAt,
                                'status' => ShuttleTripStatus::Proposed->value,
                                'capacity' => $vehicle?->seats ?? $settings->default_capacity,
                                'is_generated' => true,
                            ]);
                            $trip->setRelation('assignments', new Collection());
                            $created++;
                        }

                        $assignable = min($remaining, $trip->remaining_seats);

                        if ($assignable < 1) {
                            throw new LogicException(__('Nessuna capienza navetta disponibile per completare la proposta.'));
                        }

                        $existingAssignment = $trip->assignments
                            ->firstWhere('reservation_id', $reservation->id);

                        if ($existingAssignment) {
                            $existingAssignment->increment('passengers', $assignable);
                        } else {
                            $trip->assignments()->create([
                                'reservation_id' => $reservation->id,
                                'passengers' => $assignable,
                            ]);
                        }
                        $trip->load('assignments');
                        $remaining -= $assignable;
                        $trip = null;
                    }
                }
            }

            return $created;
        });
    }

    public function assign(ShuttleTrip $trip, Reservation $reservation, int $passengers): void
    {
        DB::transaction(function () use ($trip, $reservation, $passengers): void {
            $trip = ShuttleTrip::query()->with('assignments')->lockForUpdate()->findOrFail($trip->id);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ((int) $trip->parking_id !== (int) $reservation->parking_id) {
                throw new LogicException(__('La prenotazione appartiene a un altro parcheggio.'));
            }

            if (! in_array($trip->status, [
                ShuttleTripStatus::Proposed,
                ShuttleTripStatus::Confirmed,
            ], true)) {
                throw new LogicException(__('Il viaggio non accetta più modifiche ai gruppi.'));
            }

            $reservationTime = $trip->direction === ShuttleDirection::Outbound
                ? $reservation->starts_at
                : $reservation->ends_at;

            if (! $reservationTime->isSameDay($trip->scheduled_at)) {
                throw new LogicException(__('La prenotazione non appartiene alla giornata del viaggio.'));
            }

            $alreadyOnTrip = (int) $trip->assignments
                ->firstWhere('reservation_id', $reservation->id)?->passengers;
            $remainingForDirection = $this->remainingPassengers($reservation, $trip->direction) + $alreadyOnTrip;
            $remainingSeats = $trip->remaining_seats + $alreadyOnTrip;

            if ($passengers < 1 || $passengers > $remainingForDirection) {
                throw new LogicException(__('Il numero di passeggeri supera quelli ancora da assegnare.'));
            }

            if ($passengers > $remainingSeats) {
                throw new LogicException(__('I posti rimasti sulla navetta non sono sufficienti.'));
            }

            $trip->assignments()->updateOrCreate(
                ['reservation_id' => $reservation->id],
                ['passengers' => $passengers],
            );
        });
    }

    public function remainingPassengers(Reservation $reservation, ShuttleDirection $direction): int
    {
        $assigned = $reservation->shuttleAssignments()
            ->whereHas('trip', fn ($query) => $query
                ->where('direction', $direction->value)
                ->where('status', '!=', ShuttleTripStatus::Cancelled->value))
            ->sum('passengers');

        return max(0, (int) ($reservation->passengers_count ?? 1) - (int) $assigned);
    }

    private function matchingTrip(
        Parking $parking,
        ShuttleDirection $direction,
        Carbon $desiredAt,
        int $windowMinutes,
    ): ?ShuttleTrip {
        return ShuttleTrip::query()
            ->where('parking_id', $parking->id)
            ->where('direction', $direction->value)
            ->whereIn('status', [
                ShuttleTripStatus::Proposed->value,
                ShuttleTripStatus::Confirmed->value,
            ])
            ->whereBetween('scheduled_at', [
                $desiredAt->copy()->subMinutes($windowMinutes),
                $desiredAt->copy()->addMinutes($windowMinutes),
            ])
            ->with('assignments')
            ->get()
            ->sortBy(fn (ShuttleTrip $trip) => abs($trip->scheduled_at->diffInMinutes($desiredAt)))
            ->first(fn (ShuttleTrip $trip) => $trip->remaining_seats > 0);
    }

    private function availableVehicle(
        Collection $vehicles,
        Carbon $scheduledAt,
        int $turnaroundMinutes,
    ): ?ShuttleVehicle {
        return $vehicles->first(function (ShuttleVehicle $vehicle) use ($scheduledAt, $turnaroundMinutes): bool {
            return ! ShuttleTrip::query()
                ->where('shuttle_vehicle_id', $vehicle->id)
                ->where('status', '!=', ShuttleTripStatus::Cancelled->value)
                ->whereBetween('scheduled_at', [
                    $scheduledAt->copy()->subMinutes($turnaroundMinutes),
                    $scheduledAt->copy()->addMinutes($turnaroundMinutes),
                ])
                ->exists();
        });
    }
}
