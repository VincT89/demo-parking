<?php

namespace App\Integrations\Adapters;

use App\Integrations\AbstractPlatformAdapter;
use App\Models\ParkingListing;
use Carbon\Carbon;
use App\Integrations\Support\RooshProviderClient;
use App\Integrations\DTO\NormalizedReservation;
use App\Integrations\Support\ValetDetector;
use App\Models\PlatformProductMapping;
use Illuminate\Support\Facades\Log;

class VologioAdapter extends AbstractPlatformAdapter
{
    private RooshProviderClient $client;

    private ?array $valetServices = null;

    public function __construct(RooshProviderClient $client)
    {
        $this->client = $client;
    }

    public function getName(): string
    {
        return 'Vologio';
    }

    public function getPlatformSlug(): string
    {
        return 'vologio';
    }

    private function serviceTypes(): array
    {
        return $this->valetServices ??= ValetDetector::rooshServices($this->client->getServiceLocations());
    }

    public function classifyValet(array $payload): ?bool
    {
        return ValetDetector::fromPayload('vologio', $payload, $this->serviceTypes());
    }

    public function defaultSyncWindow(): array
    {
        return [
            Carbon::now()->subHours((int) $this->configurationOption('vologio', 'sync_lookback_hours', 72)),
            Carbon::now(),
        ];
    }

    public function fetchReservations(ParkingListing $listing, Carbon $from, Carbon $to, string $mode = 'modified'): array
    {
        $externalLocationId = $listing->external_id;

        if (empty($externalLocationId)) {
            throw new \RuntimeException("ParkingListing (ID: {$listing->id}) non ha un external_id configurato.");
        }

        $this->valetServices = null;
        try {
            $this->serviceTypes();
        } catch (\Exception $e) {
            /** Preserve ordinary imports when metadata is unavailable, without guessing additional locations. */
            $this->valetServices = [];
            Log::warning('Catalogo Roosh non disponibile: classificazione valet e sedi aggiuntive non verificate.', ['listing_id' => $listing->id]);
        }

        $mappedServices = PlatformProductMapping::query()
            ->where('platform_id', $listing->platform_id)
            ->whereHas('parkingProduct', fn ($query) => $query->where('parking_id', $listing->parking_id))
            ->active()
            ->pluck('external_ref');
        $additionalValetServices = [];
        foreach ($mappedServices as $serviceId) {
            $service = $this->valetServices[(string) $serviceId] ?? null;
            /** An account's other locations are authorized only by an explicit product mapping for this parking. */
            if ($service && $service['is_valet'] === true && $service['location_id'] !== null
                && $service['location_id'] !== (string) $externalLocationId) {
                $additionalValetServices[(string) $serviceId] = $service['location_id'];
            }
        }

        if ($mode === 'stay_period') {
            $records = $this->client->findBookingsByServiceLocationId([
                $listing->external_id
            ]);
            if ($additionalValetServices !== []) {
                $records = collect($records)
                    ->merge($this->client->findBookingsByServiceId(array_keys($additionalValetServices)))
                    ->unique(fn ($record) => (string) ($record['id'] ?? json_encode($record)))
                    ->values()
                    ->all();
            }
        } else {
            $records = $this->client->findBookingsByModification($from, $to);
        }

        return collect($records)
            ->filter(function ($record) use ($from, $to, $mode, $externalLocationId, $additionalValetServices) {
                $locationId = $record['service_location_id']
                    ?? $record['service_location']['id']
                    ?? $record['serviceLocation']['id']
                    ?? null;

                $mappedValetLocation = $additionalValetServices[(string) ($record['service_id'] ?? '')] ?? null;
                if ((string) $locationId !== (string) $externalLocationId
                    && ($mappedValetLocation === null || (string) $locationId !== $mappedValetLocation)) {
                    return false;
                }

                if ($mode !== 'stay_period') {
                    return true;
                }

                $startRaw = $record['start'] ?? $record['start_at'] ?? null;
                $endRaw = $record['end'] ?? $record['end_at'] ?? null;

                if (!$startRaw || !$endRaw) {
                    return false;
                }

                $start = Carbon::parse($startRaw, 'Europe/Rome');
                $end = Carbon::parse($endRaw, 'Europe/Rome');

                return $start->lte($to) && $end->gte($from);
            })
            ->map(fn ($record) => $this->normalizeRecord($record))
            ->values()
            ->all();
    }

    private function passengersCount(array $record): int
    {
        $value = $record['journey']['travelers'] ?? null;

        return is_numeric($value) && (int) $value > 0
            ? (int) $value
            : 1;
    }

    protected function normalizeRecord(array $record): NormalizedReservation
    {
        if (empty($record['id'])) {
            throw new \RuntimeException('Missing required field: id');
        }

        if (empty($record['service_id'])) {
            throw new \RuntimeException("Missing required field: service_id for {$record['id']}");
        }

        if (empty($record['start']) || empty($record['end'])) {
            throw new \RuntimeException("Missing period dates for {$record['id']}");
        }

        $startsAt = Carbon::parse($record['start'], 'Europe/Rome');
        $endsAt = Carbon::parse($record['end'], 'Europe/Rome');

        $customerName = trim(
            ($record['customer']['first_name'] ?? '') . ' ' .
            ($record['customer']['last_name'] ?? '')
        );

        if ($customerName === '') {
            $customerName = $record['customer']['name']
                ?? $record['customer']['full_name']
                ?? $record['customer_name']
                ?? $record['name']
                ?? 'Sconosciuto';
        }

        $departureFlight = $record['journey']['departure_flight_number'] ?? null;
        $arrivalFlight = $record['journey']['arrival_flight_number'] ?? null;
        $flightReference = collect([$departureFlight, $arrivalFlight])->filter()->join(' / ') ?: null;

        $spots = 1;

        $rawStatus = strtolower((string) ($record['status'] ?? ''));
        $mappedStatus = match($rawStatus) {
            'cancelled', 'canceled' => 'cancelled',
            'pending' => 'pending',
            default => 'confirmed',
        };

        $startRaw = $record['start'] ?? $record['start_at'] ?? null;
        $endRaw = $record['end'] ?? $record['end_at'] ?? null;

        if (!$startRaw || !$endRaw) {
            throw new \RuntimeException("Missing period dates for {$record['id']}");
        }

        $startsAt = Carbon::parse($startRaw, 'Europe/Rome');
        $endsAt = Carbon::parse($endRaw, 'Europe/Rome');

        return new NormalizedReservation(
            external_id: (string) ($record['id'] ?? ''),
            external_product_ref: (string) ($record['service_id'] ?? ''),
            external_product_name: null,
            customer_name: $customerName,
            customer_email: $record['customer']['email'] ?? $record['email'] ?? null,
            customer_phone: $record['customer']['phone'] ?? $record['phone'] ?? null,
            license_plate: $record['journey']['car']['license_plate'] ?? $record['license_plate'] ?? null,
            starts_at: $startsAt,
            ends_at: $endsAt,
            spots: $spots,
            price: isset($record['price']['amount']) ? (float) $record['price']['amount'] : null,
            currency: $record['price']['currency'] ?? 'EUR',
            notes: $record['remarks'] ?? null,
            raw_data: $record,
            status: $mappedStatus,
            flight_reference: $flightReference,
            passengers_count: $this->passengersCount($record),
            is_valet: $this->classifyValet($record)
        );
    }
}
