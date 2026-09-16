<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerBackfillService
{
    private const SOURCES = [
        'subscription' => ParkingSubscription::class,
        'reservation' => Reservation::class,
        'stay' => ParkingStay::class,
    ];

    private array $identities = [];
    private array $customers = [];
    private array $report = [];

    public function run(): array
    {
        return DB::transaction(function () {
            $this->identities = [];
            $this->customers = [];
            $this->report = [
                'created' => 0, 'vehicles_added' => 0,
                'linked' => array_fill_keys(array_keys(self::SOURCES), 0),
                'skipped' => 0, 'issues' => [], 'remaining' => 0,
            ];

            Customer::query()->with('vehicles')->chunkById(300, function ($customers) {
                foreach ($customers as $customer) {
                    $this->customers[$customer->id] = $customer;
                    $data = ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone, 'plate' => null];
                    $this->remember($data, $customer->id);
                    foreach ($customer->vehicles as $vehicle) {
                        $this->remember(array_replace($data, ['plate' => $vehicle->license_plate]), $customer->id);
                    }
                }
            });

            // Explicit historical links remain useful even after editing a customer profile.
            // A subscription driver's details are not an identity for the contract holder.
            foreach (self::SOURCES as $kind => $model) {
                $model::query()->whereNotNull('customer_id')
                    ->when($kind === 'stay', fn ($query) => $query->whereNull('parking_subscription_id'))
                    ->select(['id', 'customer_id', 'customer_name', 'customer_email', 'customer_phone', 'license_plate'])
                    ->chunkById(300, function ($records) {
                        foreach ($records as $record) {
                            if (isset($this->customers[$record->customer_id])) {
                                $this->remember($this->data($record), $record->customer_id);
                            }
                        }
                    });
            }

            foreach (self::SOURCES as $kind => $model) {
                $model::query()->whereNull('customer_id')->select('id')
                    ->chunkById(300, function ($records) use ($kind, $model) {
                        foreach ($records as $record) {
                            $this->import($kind, $model, $record->id);
                        }
                    });
                $this->report['remaining'] += $model::query()->whereNull('customer_id')->count();
            }

            return $this->report;
        }, 3);
    }

    private function import(string $kind, string $model, int $id): void
    {
        $subscription = null;
        if ($kind === 'stay') {
            $subscriptionId = ParkingStay::query()->findOrFail($id)->parking_subscription_id;
            if ($subscriptionId) {
                $subscription = ParkingSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            }
        }
        $record = $model::query()->lockForUpdate()->findOrFail($id);
        if ($record->customer_id) {
            return;
        }

        if ($subscription) {
            if (! $subscription->customer_id) {
                $this->skip($kind, $id, 'parent_unlinked');
                return;
            }
            // The parent link is authoritative, including for archived contract holders.
            $customer = Customer::query()->lockForUpdate()->findOrFail($subscription->customer_id);
            $this->attach($record, $customer, $kind);
            return;
        }

        // Do not guess the owner of a contract whose stays were linked independently.
        if ($kind === 'subscription' && $record->stays()->whereNotNull('customer_id')->exists()) {
            $this->skip($kind, $id, 'conflicting_links');
            return;
        }

        $data = $this->data($record);
        if ($data['name'] === '') {
            $this->skip($kind, $id, 'missing_name');
            return;
        }
        if (mb_strlen($data['name']) > 255 || mb_strlen($data['phone'] ?? '') > 50
            || ($data['email'] && (mb_strlen($data['email']) > 255 || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)))) {
            $this->skip($kind, $id, 'invalid_data');
            return;
        }

        $keys = $this->keys($data);
        $ids = [];
        foreach ($keys as $key) {
            foreach ($this->identities[$key] ?? [] as $customerId => $_) {
                $ids[$customerId] = true;
            }
        }
        if (count($ids) > 1) {
            $this->skip($kind, $id, 'ambiguous');
            return;
        }

        if ($ids) {
            $customerId = (int) array_key_first($ids);
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            if (! $customer->is_active) {
                $this->skip($kind, $id, 'archived');
                return;
            }

            // A weaker match cannot override a different, already known email/phone.
            $matchedPrimary = isset($this->identities[reset($keys)][$customerId]);
            if (! $matchedPrimary && (
                ($data['email'] && $customer->email && $data['email'] !== Str::lower(trim($customer->email)))
                || ($this->phone($data['phone']) && $this->phone($customer->phone)
                    && $this->phone($data['phone']) !== $this->phone($customer->phone))
            )) {
                $this->skip($kind, $id, 'ambiguous');
                return;
            }
        } else {
            // With only a name, keep a separate profile for this source operation.
            // Its persisted customer_id makes subsequent runs idempotent.
            $customer = Customer::query()->create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
            ]);
            $this->customers[$customer->id] = $customer;
            $this->report['created']++;
        }

        $this->attach($record, $customer, $kind);
        $this->remember($data, $customer->id);
    }

    private function attach(Model $record, Customer $customer, string $kind): void
    {
        // Only add the relationship: retain snapshot fields and original timestamps.
        $updated = DB::table($record->getTable())->where('id', $record->id)
            ->whereNull('customer_id')->update(['customer_id' => $customer->id]);
        if (! $updated) {
            return;
        }
        $this->report['linked'][$kind]++;

        $plate = $this->plate($record->license_plate);
        if ($plate && $customer->is_active && ! $customer->vehicles()->where('license_plate', $plate)->exists()) {
            if ($customer->vehicles()->count() >= 30) {
                $this->report['issues'][] = compact('kind') + ['id' => $record->id, 'reason' => 'plate_limit'];
                return;
            }
            $customer->vehicles()->firstOrCreate(['license_plate' => $plate]);
            $this->report['vehicles_added']++;
            $this->remember([
                'name' => $customer->name, 'email' => $customer->email,
                'phone' => $customer->phone, 'plate' => $plate,
            ], $customer->id);
        }
    }

    private function data(Model $record): array
    {
        return [
            'name' => Str::squish((string) $record->customer_name),
            'email' => filled($record->customer_email) ? Str::lower(trim($record->customer_email)) : null,
            'phone' => filled($record->customer_phone) ? trim($record->customer_phone) : null,
            'plate' => $record->license_plate,
        ];
    }

    private function keys(array $data): array
    {
        $name = Str::lower(Str::squish((string) $data['name']));
        if ($name === '') {
            return [];
        }
        $keys = [];
        foreach ([
            'email' => filled($data['email']) ? Str::lower(trim($data['email'])) : null,
            'phone' => $this->phone($data['phone']),
            'plate' => $this->plate($data['plate']),
        ] as $type => $value) {
            if ($value) {
                $keys[] = $type.':'.hash('sha256', json_encode([$name, $value], JSON_UNESCAPED_UNICODE));
            }
        }
        return $keys;
    }

    private function phone(?string $value): ?string
    {
        $value = Customer::normalizePhone($value);
        return strlen($value) >= 7 && ! preg_match('/^(\d)\1+$/', $value) ? $value : null;
    }

    private function plate(?string $value): ?string
    {
        $value = Customer::normalizePlate($value ?? '');
        return preg_match('/^[A-Z0-9]{3,32}$/', $value) ? $value : null;
    }

    private function remember(array $data, int $customerId): void
    {
        foreach ($this->keys($data) as $key) {
            $this->identities[$key][$customerId] = true;
        }
    }

    private function skip(string $kind, int $id, string $reason): void
    {
        $this->report['skipped']++;
        $this->report['issues'][] = compact('kind', 'id', 'reason');
    }
}
