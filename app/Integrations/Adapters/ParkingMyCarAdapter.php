<?php

namespace App\Integrations\Adapters;

use App\Integrations\AbstractPlatformAdapter;
use App\Integrations\DTO\NormalizedReservation;
use App\Integrations\Support\ParkingMyCarClient;
use App\Models\ParkingListing;
use Carbon\Carbon;

class ParkingMyCarAdapter extends AbstractPlatformAdapter
{
    public function __construct(
        private ParkingMyCarClient $client
    ) {}

    public function getName(): string
    {
        return 'ParkingMyCar';
    }

    public function getPlatformSlug(): string
    {
        return 'parking-my-car';
    }

    public function defaultSyncWindow(): array
    {
        return [
            Carbon::now()->subHours((int) config('services.parking_my_car.sync_lookback_hours', 72)),
            Carbon::now(),
        ];
    }

    public function fetchReservations(ParkingListing $listing, Carbon $from, Carbon $to, string $mode = 'modified'): array
    {
        if (empty($listing->external_id)) {
            throw new \RuntimeException("ParkingListing ID {$listing->id} non ha external_id configurato.");
        }

        if ($mode === 'stay_period') {
            $apiFrom = $from->copy()->subMonths(6);
            $records = $this->client->findBookingsByPeriod($apiFrom, $to);
        } else {
            $updatedRecords = $this->client->findBookingsByModification($from, $to);

            $periodFrom = $from->copy()->subDays(
                (int) config('services.parking_my_car.operational_past_days', 1)
            );

            $periodTo = Carbon::now()->addDays(
                (int) config('services.parking_my_car.operational_future_days', 60)
            );

            $periodRecords = $this->client->findBookingsByPeriod($periodFrom, $periodTo);

            $records = collect($updatedRecords)
                ->merge($periodRecords)
                ->unique(fn ($record) => (string) (
                    $record['id']
                    ?? $record['booking_id']
                    ?? $record['reservation_id']
                    ?? json_encode($record)
                ))
                ->values()
                ->all();

            if (config('services.parking_my_car.debug_sync')) {
                \Illuminate\Support\Facades\Log::debug('PMC sync merged records', [
                    'listing_id' => $listing->id,
                    'parking_id' => $listing->external_id,
                    'updated_count' => count($updatedRecords),
                    'period_count' => count($periodRecords),
                    'merged_count' => count($records),
                    'ids' => collect($records)->pluck('id')->take(50)->values()->all(),
                ]);
            }
        }

        if (config('services.parking_my_car.debug_sync')) {
            \Log::debug('PMC bookings_resource ids after API', [
                'ids' => collect($records)->pluck('id')->values()->all(),
            ]);
        }

        $filtered = collect($records)
            ->filter(fn (array $record) => (string)($record['parking_id'] ?? $record['parking']['id'] ?? '') === (string)$listing->external_id)
            ->filter(function (array $record) use ($from, $to, $mode) {
                if ($mode !== 'stay_period') {
                    return true;
                }

                $startsAtRaw = $record['in_dttm'] ?? $record['start_dtm'] ?? $record['start_dttm'] ?? $record['date_start'] ?? $record['start_date'] ?? null;
                $endsAtRaw = $record['out_dttm'] ?? $record['end_dtm'] ?? $record['end_dttm'] ?? $record['date_end'] ?? $record['end_date'] ?? null;

                if (!$startsAtRaw || !$endsAtRaw) {
                    return false;
                }

                $startsAt = is_numeric($startsAtRaw)
                    ? Carbon::createFromTimestamp($startsAtRaw, 'Europe/Rome')
                    : Carbon::parse($startsAtRaw, 'Europe/Rome');
                $endsAt = is_numeric($endsAtRaw)
                    ? Carbon::createFromTimestamp($endsAtRaw, 'Europe/Rome')
                    : Carbon::parse($endsAtRaw, 'Europe/Rome');

                return $startsAt->lte($to) && $endsAt->gte($from);
            })
            ->values()
            ->all();

        if (config('services.parking_my_car.debug_sync')) {
            \Log::debug('PMC bookings_resource ids after filters', [
                'ids' => collect($filtered)->pluck('id')->values()->all(),
            ]);
        }

        return collect($filtered)
            ->map(fn (array $record) => $this->normalizeRecord($record))
            ->values()
            ->all();
    }

    private function passengersCount(array $record): int
    {
        $value = $record['seats'] ?? null;

        return is_numeric($value) && (int) $value > 0
            ? (int) $value
            : 1;
    }

    private function parsePlatformTimestamp(array $record, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $record) || $record[$key] === null || $record[$key] === '') {
                continue;
            }

            $value = $record[$key];

            return is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value, 'Europe/Rome')
                : Carbon::parse((string) $value, 'Europe/Rome');
        }

        return null;
    }

    private function parseOptionalPlatformTimestamp(array $record, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $record)) {
                continue;
            }

            $value = $record[$key];

            if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0') {
                continue;
            }

            return is_numeric($value)
                ? Carbon::createFromTimestamp((int) $value, 'Europe/Rome')
                : Carbon::parse((string) $value, 'Europe/Rome');
        }

        return null;
    }

    protected function normalizeRecord(array $record): NormalizedReservation
    {
        $externalId = $record['id'] ?? $record['booking_id'] ?? $record['reservation_id'] ?? null;

        if (!$externalId) {
            throw new \RuntimeException('ParkingMyCar: campo id prenotazione mancante.');
        }

        $startsAtRaw = $record['in_dttm']
            ?? $record['start_dtm']
            ?? $record['start_dttm']
            ?? $record['date_start']
            ?? $record['start_date']
            ?? $record['checkin_at']
            ?? $record['entry_at']
            ?? null;

        $endsAtRaw = $record['out_dttm']
            ?? $record['end_dtm']
            ?? $record['end_dttm']
            ?? $record['date_end']
            ?? $record['end_date']
            ?? $record['checkout_at']
            ?? $record['exit_at']
            ?? null;

        if (!$startsAtRaw || !$endsAtRaw) {
            \Log::error('ParkingMyCar missing dates JSON: ' . json_encode($record));
            throw new \RuntimeException("ParkingMyCar: date mancanti per prenotazione {$externalId}.");
        }

        $startsAt = is_numeric($startsAtRaw)
            ? Carbon::createFromTimestamp($startsAtRaw, 'Europe/Rome')
            : Carbon::parse($startsAtRaw, 'Europe/Rome');
        $endsAt = is_numeric($endsAtRaw)
            ? Carbon::createFromTimestamp($endsAtRaw, 'Europe/Rome')
            : Carbon::parse($endsAtRaw, 'Europe/Rome');

        if ($endsAt->isBefore($startsAt)) {
            throw new \RuntimeException("ParkingMyCar: ends_at prima di starts_at per prenotazione {$externalId}.");
        }

        $customerName = trim(
            ($record['customer']['first_name'] ?? $record['first_name'] ?? '') . ' ' .
            ($record['customer']['last_name'] ?? $record['last_name'] ?? '')
        );

        if ($customerName === '') {
            $customerName = $record['customer']['name']
                ?? $record['customer_name']
                ?? $record['name']
                ?? $record['user']
                ?? 'Sconosciuto';
        }

        $externalProductRef =
            $record['parking_model_id']
            ?? $record['model_id']
            ?? $record['product_id']
            ?? $record['service_id']
            ?? $record['rate_id']
            ?? $record['parking_type_id']
            ?? config('services.parking_my_car.default_product_ref', 'DEFAULT');

        $flightReference = collect([
            $record['departure_flight'] ?? null,
            $record['arrival_flight'] ?? null,
            $record['flight_reference'] ?? null,
            $record['flight_number'] ?? null,
        ])->filter()->unique()->join(' / ') ?: null;

        $platformCancelledAt = $this->parseOptionalPlatformTimestamp($record, [
            'cancelled',
            'cancelled_dttm',
            'cancelled_at',
            'canceled',
            'canceled_dttm',
            'canceled_at',
        ]);

        $status = $this->mapStatus($record['status'] ?? null);

        if ($platformCancelledAt !== null) {
            $status = 'cancelled';
        }

        return new NormalizedReservation(
            external_id: (string) $externalId,
            external_product_ref: (string) $externalProductRef,
            external_product_name: $record['product_name'] ?? $record['service_name'] ?? null,
            customer_name: $customerName,
            customer_email: $record['customer']['email'] ?? $record['email'] ?? null,
            customer_phone: $record['customer']['phone'] ?? $record['phone'] ?? $record['mobile'] ?? null,
            license_plate: $record['vehicle']['license_plate'] ?? $record['license_plate'] ?? $record['plate'] ?? null,
            starts_at: $startsAt,
            ends_at: $endsAt,
            spots: (int) ($record['spots'] ?? 1),
            price: isset($record['price']) ? (float) $record['price'] : (isset($record['total']) ? (float) $record['total'] : null),
            currency: $record['currency'] ?? 'EUR',
            notes: $record['notes'] ?? $record['note'] ?? null,
            raw_data: $record,
            status: $status,
            flight_reference: $flightReference,
            passengers_count: $this->passengersCount($record),
            platform_created_at: $this->parsePlatformTimestamp($record, [
                'created',
                'created_dttm',
                'created_at',
            ]),
            platform_updated_at: $this->parsePlatformTimestamp($record, [
                'updated',
                'updated_dttm',
                'updated_at',
            ]),
            platform_cancelled_at: $platformCancelledAt
        );
    }

    protected function mapStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'cancelled',
            'canceled',
            'annullata',
            'annullato',
            'cancellata',
            'cancellato' => 'cancelled',

            'pending',
            'waiting',
            'in_attesa',
            'attesa' => 'pending',

            'approvata',
            'approvato',
            'confermata',
            'confermato',
            'completata',
            'completato',
            'completed',
            'approved',
            'confirmed' => 'confirmed',

            default => 'confirmed',
        };
    }
}
