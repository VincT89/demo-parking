<?php

namespace App\Integrations\Adapters;

use App\Integrations\AbstractPlatformAdapter;
use App\Models\ParkingListing;
use Carbon\Carbon;
use App\Integrations\DTO\NormalizedReservation;
use App\Integrations\Support\FixturePayloadReader;
use App\Integrations\Support\ParkosClient;

class ParkosAdapter extends AbstractPlatformAdapter
{
    public function __construct(
        private ParkosClient $client,
        private FixturePayloadReader $fixtureReader
    ) {}

    public function getName(): string
    {
        return 'Parkos';
    }

    public function getPlatformSlug(): string
    {
        return 'parkos';
    }

    public function defaultSyncWindow(): array
    {
        return [
            Carbon::now()->subHours((int) config('services.parkos.sync_lookback_hours', 72)),
            Carbon::now(),
        ];
    }

    public function fetchReservations(ParkingListing $listing, Carbon $from, Carbon $to, string $mode = 'modified'): array
    {
        if (config('services.parkos.fixture_mode')) {
            $fixtureFile = config('services.parkos.fixture_file', 'reservations_success.json');
            $payload = $this->fixtureReader->loadFixture('parkos', $fixtureFile);

            if (!isset($payload['data']) || !is_array($payload['data'])) {
                throw new \RuntimeException('Invalid shape: missing or invalid "data" key.');
            }

            return collect($payload['data'])
                ->map(fn (array $record) => $this->normalizeRecord($record))
                ->values()
                ->all();
        }

        if (empty($listing->external_id)) {
            throw new \RuntimeException("ParkingListing ID {$listing->id} non ha external_id configurato.");
        }

        if ($mode === 'stay_period') {
            $apiFrom = $from->copy()->subMonths(6);
            $arrival = $this->client->findBookingsByPeriodType($apiFrom, $to, 'arrival', $listing->external_id);
            $departure = $this->client->findBookingsByPeriodType($apiFrom, $to, 'departure', $listing->external_id);

            $records = collect($arrival)
                ->merge($departure)
                ->keyBy(fn ($record) => $record['code'] ?? json_encode($record))
                ->values()
                ->all();
        } else {
            $updatedRecords = $this->client->findBookingsByModification($from, $to, $listing->external_id);
            $createdRecords = $this->client->findBookingsByCreation($from, $to, $listing->external_id);
            $cancelledRecords = $this->client->findBookingsByCancellation($from, $to, $listing->external_id);

            $records = collect($updatedRecords)
                ->merge($createdRecords)
                ->merge($cancelledRecords)
                ->keyBy(fn ($record) => $record['code'] ?? json_encode($record))
                ->values()
                ->all();

            if (config('services.parkos.debug_sync', false)) {
                \Illuminate\Support\Facades\Log::debug('Parkos sync records merged', [
                    'listing_id' => $listing->id,
                    'merchant_id' => $listing->external_id,
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'updated_count' => count($updatedRecords),
                    'created_count' => count($createdRecords),
                    'cancelled_count' => count($cancelledRecords),
                    'merged_count' => count($records),
                ]);

                foreach ($records as $rec) {
                    \Illuminate\Support\Facades\Log::debug('Parkos booking received', [
                        'code' => $rec['code'] ?? null,
                        'customer' => $rec['name'] ?? null,
                    ]);
                }
            }
        }

        return collect($records)
            ->filter(function ($record) use ($from, $to, $mode) {
                if ($mode !== 'stay_period') {
                    return true;
                }

                $arrivalDate = $record['arrival_date'] ?? null;
                $arrivalTime = $record['arrival_time'] ?? '00:00:00';
                $departureDate = $record['departure_date'] ?? null;
                $departureTime = $record['departure_time'] ?? '23:59:59';

                if (!$arrivalDate || !$departureDate) {
                    return false;
                }

                $start = Carbon::parse("{$arrivalDate} {$arrivalTime}", 'Europe/Rome');
                $end = Carbon::parse("{$departureDate} {$departureTime}", 'Europe/Rome');

                return $start->lte($to) && $end->gte($from);
            })
            ->map(fn (array $record) => $this->normalizeRecord($record))
            ->values()
            ->all();
    }

    private function passengersCount(array $record): int
    {
        $value = $record['persons'] ?? null;

        return is_numeric($value) && (int) $value > 0
            ? (int) $value
            : 1;
    }

    private function parsePlatformDateTime(array $record, string $key): ?Carbon
    {
        if (! array_key_exists($key, $record) || $record[$key] === null || $record[$key] === '') {
            return null;
        }

        return Carbon::parse((string) $record[$key], 'Europe/Rome');
    }

    protected function normalizeRecord(array $record): NormalizedReservation
    {
        if (empty($record['code'])) {
            throw new \RuntimeException('Missing required field: code');
        }

        if (empty($record['arrival_date']) || empty($record['arrival_time'])) {
            throw new \RuntimeException("Missing arrival dates for {$record['code']}");
        }
        
        if (empty($record['departure_date']) || empty($record['departure_time'])) {
            throw new \RuntimeException("Missing departure dates for {$record['code']}");
        }

        try {
            $startsAt = Carbon::parse($record['arrival_date'] . ' ' . $record['arrival_time'], 'Europe/Rome');
            $endsAt = Carbon::parse($record['departure_date'] . ' ' . $record['departure_time'], 'Europe/Rome');
        } catch (\Exception $e) {
            throw new \RuntimeException("Invalid date format for {$record['code']}");
        }

        if ($endsAt->isBefore($startsAt)) {
            throw new \RuntimeException("Invalid dates: ends_at before starts_at for {$record['code']}");
        }

        $merchantId = $record['merchant_id'] ?? '';
        $parkingType = $record['parking_type'] ?? '';
        $locationType = $record['location_type'] ?? '';
        $externalProductRef = "{$merchantId}:{$parkingType}:{$locationType}";

        $status = !empty($record['cancelled_at']) ? 'cancelled' : 'confirmed';

        $customerName = trim($record['name'] ?? 'Sconosciuto');

        $price = isset($record['total_price']) && is_numeric($record['total_price']) ? (float) $record['total_price'] : null;
        $currency = $record['currency'] ?? 'EUR';

        return new NormalizedReservation(
            external_id: (string) $record['code'],
            external_product_ref: $externalProductRef,
            external_product_name: null,
            customer_name: $customerName,
            customer_email: $record['email'] ?? null,
            customer_phone: $record['phone'] ?? null,
            license_plate: $record['car_license_plate'] ?? null,
            starts_at: $startsAt,
            ends_at: $endsAt,
            spots: (int) ($record['spots'] ?? 1),
            price: $price,
            currency: $currency,
            notes: $record['notes'] ?? null,
            raw_data: $record,
            status: $status,
            passengers_count: $this->passengersCount($record),
            platform_created_at: $this->parsePlatformDateTime($record, 'created_at'),
            platform_updated_at: $this->parsePlatformDateTime($record, 'updated_at'),
            platform_cancelled_at: $this->parsePlatformDateTime($record, 'cancelled_at')
        );
    }
}
