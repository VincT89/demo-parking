<?php

namespace App\Services;

use App\Enums\GarageRateKind;
use App\Enums\SubscriptionStatus;
use App\Models\GarageRate;
use App\Models\ParkingSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ParkingSubscriptionService
{
    public function create(array $data, User $operator): ParkingSubscription
    {
        return DB::transaction(function () use ($data, $operator): ParkingSubscription {
            $rate = GarageRate::query()->lockForUpdate()->findOrFail($data['garage_rate_id']);

            $startsOn = Carbon::parse($data['starts_on'])->startOfDay();

            if (! $rate->is_active
                || $rate->kind !== GarageRateKind::Subscription
                || ! $rate->isEffectiveOn($startsOn)) {
                throw new LogicException(__('La tariffa selezionata non è un abbonamento attivo.'));
            }

            if ((int) $rate->parking_id !== (int) $data['parking_id']
                || (int) $rate->parking_product_id !== (int) $data['parking_product_id']) {
                throw new LogicException(__('La tariffa non appartiene al parcheggio e alla categoria selezionati.'));
            }

            $endsOn = filled($data['ends_on'] ?? null)
                ? Carbon::parse($data['ends_on'])->endOfDay()
                : null;

            $alreadyReserved = ParkingSubscription::query()
                ->where('parking_product_id', $rate->parking_product_id)
                ->reservingBetween($startsOn, $endsOn)
                ->lockForUpdate()
                ->sum('reserved_spots');

            if ($alreadyReserved + (int) $data['reserved_spots'] > $rate->parkingProduct->capacity) {
                throw new LogicException(__('Gli abbonamenti attivi supererebbero la capacità della categoria selezionata.'));
            }

            return ParkingSubscription::query()->create([
                ...$data,
                'reference' => $this->reference($rate->parking_id),
                'customer_name' => trim($data['customer_name']),
                'license_plate' => Str::upper(trim($data['license_plate'])),
                'status' => $data['status'] ?? SubscriptionStatus::Active->value,
                'price' => $rate->price,
                'currency' => $rate->currency,
                'next_billing_on' => $data['next_billing_on'] ?? $startsOn->toDateString(),
                'created_by' => $operator->id,
            ]);
        });
    }

    public function update(ParkingSubscription $subscription, array $data): ParkingSubscription
    {
        return DB::transaction(function () use ($subscription, $data): ParkingSubscription {
            $subscription = ParkingSubscription::query()
                ->with('parkingProduct')
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            $startsOn = Carbon::parse($data['starts_on'])->startOfDay();
            $endsOn = filled($data['ends_on'] ?? null)
                ? Carbon::parse($data['ends_on'])->endOfDay()
                : null;

            if (($data['status'] ?? $subscription->status->value) === SubscriptionStatus::Active->value) {
                $alreadyReserved = ParkingSubscription::query()
                    ->where('parking_product_id', $subscription->parking_product_id)
                    ->whereKeyNot($subscription->id)
                    ->reservingBetween($startsOn, $endsOn)
                    ->lockForUpdate()
                    ->sum('reserved_spots');

                if ($alreadyReserved + (int) $data['reserved_spots'] > $subscription->parkingProduct->capacity) {
                    throw new LogicException(__('Gli abbonamenti attivi supererebbero la capacità della categoria selezionata.'));
                }
            }

            $subscription->update([
                ...$data,
                'customer_name' => trim($data['customer_name']),
                'license_plate' => Str::upper(trim($data['license_plate'])),
            ]);

            return $subscription->refresh();
        });
    }

    private function reference(int $parkingId): string
    {
        do {
            $reference = 'ABB-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (ParkingSubscription::query()
            ->where('parking_id', $parkingId)
            ->where('reference', $reference)
            ->exists());

        return $reference;
    }
}
