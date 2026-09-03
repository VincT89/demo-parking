<?php

namespace App\Integrations\Adapters;

use App\Enums\ReservationStatus;
use App\Integrations\AbstractPlatformAdapter;
use App\Integrations\DTO\NormalizedReservation;
use App\Models\ParkingListing;
use App\Models\PlatformProductMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class DemoPlatformAdapter extends AbstractPlatformAdapter
{
    public function __construct(private readonly string $platformSlug)
    {
    }

    public function getName(): string
    {
        return 'Demo API '.strtoupper($this->platformSlug);
    }

    public function getPlatformSlug(): string
    {
        return $this->platformSlug;
    }

    public function defaultSyncWindow(): array
    {
        return [
            Carbon::today()->subDays(config('demo.days_before', 7)),
            Carbon::today()->addDays(config('demo.days_after', 23))->endOfDay(),
        ];
    }

    public function fetchReservations(
        ParkingListing $listing,
        Carbon $from,
        Carbon $to,
        string $mode = 'modified'
    ): array {
        if (! config('demo.enabled')) {
            throw new RuntimeException('The simulated API can only run in demo mode.');
        }

        if (! app()->environment('testing')) {
            $latency = max(0, min(2000, (int) config('demo.api_latency_ms', 650)));
            usleep($latency * 1000);
        }

        $mappings = PlatformProductMapping::query()
            ->where('platform_id', $listing->platform_id)
            ->active()
            ->whereHas('parkingProduct', function ($query) use ($listing) {
                $query->where('parking_id', $listing->parking_id)
                    ->where('is_active', true);
            })
            ->with('parkingProduct')
            ->orderBy('id')
            ->get();

        if ($mappings->isEmpty()) {
            throw new RuntimeException("No demo product mappings are configured for {$this->platformSlug}.");
        }

        $cycleKey = "demo-api-cycle:{$listing->id}";
        $cycle = (int) Cache::get($cycleKey, 0);
        Cache::put($cycleKey, $cycle + 1, now()->addDays(7));

        $demoStart = Carbon::today()->subDays(config('demo.days_before', 7))->startOfDay();
        $demoEnd = Carbon::today()->addDays(config('demo.days_after', 23))->endOfDay();
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $to->copy()->endOfDay();

        if ($windowStart->lt($demoStart)) {
            $windowStart = $demoStart;
        }
        if ($windowEnd->gt($demoEnd)) {
            $windowEnd = $demoEnd;
        }
        if ($windowStart->gt($windowEnd)) {
            return [];
        }

        $records = [];
        $sequence = 0;
        $basePerDay = max(1, min(8, (int) config('demo.reservations_per_day', 3)));

        for ($day = $windowStart->copy(); $day->lte($windowEnd); $day->addDay()) {
            $daySeed = "{$this->platformSlug}:{$listing->id}:{$day->toDateString()}";
            $dailyCount = max(1, $basePerDay + $this->number($daySeed.':volume', -1, 1));

            for ($slot = 1; $slot <= $dailyCount; $slot++) {
                $sequence++;
                $seed = "{$daySeed}:{$slot}";
                $mapping = $mappings[$this->number($seed.':mapping', 0, $mappings->count() - 1)];
                $startsAt = $day->copy()
                    ->setTime($this->number($seed.':hour', 5, 22), $this->number($seed.':quarter', 0, 3) * 15);
                $endsAt = $startsAt->copy()
                    ->addDays($this->number($seed.':stay', 1, 6))
                    ->setTime($this->number($seed.':end-hour', 6, 23), $this->number($seed.':end-quarter', 0, 3) * 15);

                $status = ReservationStatus::Confirmed;
                if ($cycle > 0 && $sequence % 19 === 0) {
                    $status = ReservationStatus::Cancelled;
                } elseif ($cycle > 0 && $sequence % 7 === 0) {
                    $status = ReservationStatus::Modified;
                }

                $records[] = $this->makeReservation(
                    listing: $listing,
                    mapping: $mapping,
                    seed: $seed,
                    externalId: $this->externalId($day, $slot),
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    status: $status,
                    cycle: $cycle,
                    mode: $mode,
                );
            }
        }

        if ($cycle > 0) {
            $extraCount = min(3, $cycle);
            for ($extra = 1; $extra <= $extraCount; $extra++) {
                $seed = "{$this->platformSlug}:{$listing->id}:extra:{$cycle}:{$extra}";
                $mapping = $mappings[$this->number($seed.':mapping', 0, $mappings->count() - 1)];
                $startsAt = Carbon::today()
                    ->addDays($this->number($seed.':day', 1, max(2, config('demo.days_after', 23))))
                    ->setTime($this->number($seed.':hour', 7, 21), 0);

                $records[] = $this->makeReservation(
                    listing: $listing,
                    mapping: $mapping,
                    seed: $seed,
                    externalId: sprintf('DEMO-%s-NEW-%02d-%02d', $this->platformCode(), $cycle, $extra),
                    startsAt: $startsAt,
                    endsAt: $startsAt->copy()->addDays($this->number($seed.':stay', 2, 5)),
                    status: ReservationStatus::Confirmed,
                    cycle: $cycle,
                    mode: $mode,
                );
            }
        }

        return $records;
    }

    private function makeReservation(
        ParkingListing $listing,
        PlatformProductMapping $mapping,
        string $seed,
        string $externalId,
        Carbon $startsAt,
        Carbon $endsAt,
        ReservationStatus $status,
        int $cycle,
        string $mode,
    ): NormalizedReservation {
        $firstNames = ['Giulia', 'Marco', 'Noor', 'Liam', 'Sofia', 'Thomas', 'Emma', 'Milan'];
        $lastNames = ['Demo', 'Example', 'Voorbeeld', 'Sample'];
        $flights = ['AZ1602', 'FR1908', 'KL2555', 'BA2606', 'HV5821', 'U24567'];
        $customerNumber = $this->number($seed.':customer', 1000, 9999);
        $firstName = $firstNames[$this->number($seed.':first-name', 0, count($firstNames) - 1)];
        $lastName = $lastNames[$this->number($seed.':last-name', 0, count($lastNames) - 1)];
        $billableDays = max(1, $startsAt->copy()->startOfDay()->diffInDays($endsAt->copy()->startOfDay()) + 1);
        $price = round(((float) $mapping->parkingProduct->price) * $billableDays, 2);
        $platformCreatedAt = $startsAt->copy()->subDays($this->number($seed.':lead-time', 4, 45));
        $cancelledAt = $status === ReservationStatus::Cancelled ? now()->copy()->subMinutes(15) : null;

        return new NormalizedReservation(
            external_id: $externalId,
            external_product_ref: $mapping->external_ref,
            external_product_name: $mapping->external_name,
            customer_name: "{$firstName} {$lastName}",
            customer_email: "demo-{$customerNumber}@example.test",
            customer_phone: '+39 000 '.str_pad((string) $customerNumber, 7, '0', STR_PAD_LEFT),
            license_plate: 'DM'.str_pad((string) $this->number($seed.':plate', 100, 999), 3, '0', STR_PAD_LEFT).'XX',
            starts_at: $startsAt,
            ends_at: $endsAt,
            spots: 1,
            price: $price,
            currency: 'EUR',
            notes: 'DEMO API · '.$listing->platform->name,
            raw_data: [
                'demo' => true,
                'simulated_api' => $this->platformSlug,
                'request_id' => 'DEMO-REQ-'.strtoupper(substr(hash('sha256', $seed.':'.$cycle), 0, 12)),
                'sync_cycle' => $cycle,
                'sync_mode' => $mode,
            ],
            status: $status->value,
            flight_reference: $flights[$this->number($seed.':flight', 0, count($flights) - 1)],
            passengers_count: $this->number($seed.':passengers', 1, 5),
            platform_created_at: $platformCreatedAt,
            platform_updated_at: $cycle > 0 ? now()->copy()->subMinutes(5) : $platformCreatedAt,
            platform_cancelled_at: $cancelledAt,
        );
    }

    private function externalId(Carbon $day, int $slot): string
    {
        return sprintf('DEMO-%s-%s-%02d', $this->platformCode(), $day->format('Ymd'), $slot);
    }

    private function platformCode(): string
    {
        return match ($this->platformSlug) {
            'parkos' => 'PKS',
            'parking-my-car' => 'PMC',
            'vologio' => 'VOL',
            default => strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $this->platformSlug), 0, 3)),
        };
    }

    private function number(string $seed, int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        $value = hexdec(substr(hash('sha256', $seed), 0, 8));

        return $min + ($value % (($max - $min) + 1));
    }
}
