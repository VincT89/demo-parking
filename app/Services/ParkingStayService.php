<?php

namespace App\Services;

use App\Enums\GarageRateKind;
use App\Enums\ParkingStayStatus;
use App\Enums\SubscriptionStatus;
use App\Models\GarageRate;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ParkingStayService
{
    public function __construct(
        private AvailabilityService $availability,
        private GaragePricingService $pricing,
    ) {}

    public function checkIn(array $data, User $operator): ParkingStay
    {
        return DB::transaction(function () use ($data, $operator): ParkingStay {
            $startsAt = Carbon::parse($data['starts_at']);
            $expectedEndsAt = Carbon::parse($data['expected_ends_at']);

            if ($expectedEndsAt->lessThanOrEqualTo($startsAt)) {
                throw new LogicException(__('L’uscita prevista deve essere successiva all’ingresso.'));
            }

            $subscription = null;
            $rate = null;
            $estimatedTotal = '0.00';

            if (filled($data['parking_subscription_id'] ?? null)) {
                $subscription = ParkingSubscription::query()
                    ->lockForUpdate()
                    ->findOrFail($data['parking_subscription_id']);

                if ($subscription->status !== SubscriptionStatus::Active
                    || (int) $subscription->parking_id !== (int) $data['parking_id']) {
                    throw new LogicException(__('L’abbonamento selezionato non è attivo per questo parcheggio.'));
                }

                if ($startsAt->toDateString() < $subscription->starts_on->toDateString()
                    || ($subscription->ends_on && $startsAt->toDateString() > $subscription->ends_on->toDateString())) {
                    throw new LogicException(__('La data di ingresso non rientra nel periodo dell’abbonamento.'));
                }

                $activeStays = ParkingStay::query()
                    ->where('parking_subscription_id', $subscription->id)
                    ->where('status', ParkingStayStatus::Active->value)
                    ->lockForUpdate()
                    ->count();

                if ($activeStays >= $subscription->reserved_spots) {
                    throw new LogicException(__('Tutti i posti riservati da questo abbonamento risultano già occupati.'));
                }

                $data['parking_product_id'] = $subscription->parking_product_id;
                if (! empty($data['customer_id']) && (int) $data['customer_id'] !== (int) $subscription->customer_id) {
                    throw new LogicException(__('Collega prima l’abbonamento allo stesso cliente.'));
                }
                $data['customer_id'] = $subscription->customer_id;
                $data['customer_name'] = $data['customer_name'] ?: $subscription->customer_name;
                $data['customer_email'] = $data['customer_email'] ?: $subscription->customer_email;
                $data['customer_phone'] = $data['customer_phone'] ?: $subscription->customer_phone;
                $data['license_plate'] = $data['license_plate'] ?: $subscription->license_plate;
            } else {
                $rate = GarageRate::query()->lockForUpdate()->findOrFail($data['garage_rate_id']);

                if (! $rate->is_active
                    || $rate->kind !== GarageRateKind::WalkIn
                    || ! $rate->isEffectiveOn($startsAt)) {
                    throw new LogicException(__('La tariffa selezionata non è valida per una sosta giornaliera.'));
                }

                if ((int) $rate->parking_id !== (int) $data['parking_id']
                    || (int) $rate->parking_product_id !== (int) $data['parking_product_id']) {
                    throw new LogicException(__('La tariffa non appartiene al parcheggio e alla categoria selezionati.'));
                }

                $availability = $this->availability->checkProductCapacity(
                    $rate->parkingProduct,
                    $startsAt,
                    $expectedEndsAt,
                    1,
                );

                if (! $availability->available) {
                    throw new LogicException($availability->reason ?? __('Nessun posto disponibile per la sosta richiesta.'));
                }

                $estimatedTotal = $this->pricing->quote($rate, $startsAt, $expectedEndsAt);
            }

            return ParkingStay::query()->create([
                ...$data,
                'customer_id' => $subscription ? $subscription->customer_id : app(CustomerRegistryService::class)->operationCustomer($data),
                'reference' => $this->reference((int) $data['parking_id']),
                'parking_subscription_id' => $subscription?->id,
                'garage_rate_id' => $rate?->id,
                'parking_product_id' => $subscription?->parking_product_id ?? $rate?->parking_product_id,
                'license_plate' => Str::upper(trim($data['license_plate'])),
                'status' => ParkingStayStatus::Active->value,
                'billing_unit' => $rate?->billing_unit->value,
                'unit_price' => $rate?->price,
                'estimated_total' => $estimatedTotal,
                'currency' => $rate?->currency ?? $subscription?->currency ?? 'EUR',
                'created_by' => $operator->id,
            ]);
        });
    }

    public function checkOut(ParkingStay $stay, Carbon $endedAt, User $operator): ParkingStay
    {
        return DB::transaction(function () use ($stay, $endedAt, $operator): ParkingStay {
            $stay = ParkingStay::query()->lockForUpdate()->findOrFail($stay->id);

            if ($stay->status !== ParkingStayStatus::Active) {
                throw new LogicException(__('La sosta non è più attiva.'));
            }

            if ($endedAt->lessThanOrEqualTo($stay->starts_at)) {
                throw new LogicException(__('L’orario di uscita deve essere successivo all’ingresso.'));
            }

            $total = '0.00';

            if (! $stay->parking_subscription_id && $stay->rate) {
                $total = $this->pricing->quote($stay->rate, $stay->starts_at, $endedAt);
            }

            $stay->update([
                'ended_at' => $endedAt,
                'status' => ParkingStayStatus::Completed->value,
                'total_amount' => $total,
                'closed_by' => $operator->id,
            ]);

            return $stay->refresh();
        });
    }

    private function reference(int $parkingId): string
    {
        do {
            $reference = 'SOSTA-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (ParkingStay::query()
            ->where('parking_id', $parkingId)
            ->where('reference', $reference)
            ->exists());

        return $reference;
    }
}
