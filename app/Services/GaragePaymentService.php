<?php

namespace App\Services;

use App\Enums\GaragePaymentMethod;
use App\Enums\GaragePaymentStatus;
use App\Models\GaragePayment;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\User;
use App\Enums\ParkingStayStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class GaragePaymentService
{
    public function recordSubscriptionPayment(
        ParkingSubscription $subscription,
        array $data,
        User $operator,
    ): GaragePayment {
        return DB::transaction(function () use ($subscription, $data, $operator): GaragePayment {
            $subscription = ParkingSubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $periodStart = Carbon::parse($data['billing_period_start'])->startOfDay();
            $periodEnd = Carbon::parse($data['billing_period_end'])->startOfDay();

            if ($periodEnd->lessThan($periodStart)) {
                throw new LogicException(__('La fine del periodo deve essere uguale o successiva all’inizio.'));
            }

            $payment = GaragePayment::query()->create([
                'parking_id' => $subscription->parking_id,
                'parking_subscription_id' => $subscription->id,
                'provider' => 'manual',
                'method' => $data['method'],
                'status' => GaragePaymentStatus::Paid->value,
                'amount' => $data['amount'],
                'currency' => $subscription->currency,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'recorded_by' => $operator->id,
                'paid_at' => Carbon::parse($data['paid_at'] ?? now()),
                'raw_data' => ['source' => 'operator'],
            ]);

            $this->syncSubscriptionPaidThrough($subscription);

            return $payment;
        });
    }

    public function recordStayPayment(ParkingStay $stay, array $data, User $operator): GaragePayment
    {
        return DB::transaction(function () use ($stay, $data, $operator): GaragePayment {
            $stay = ParkingStay::query()->lockForUpdate()->findOrFail($stay->id);

            if ($stay->parking_subscription_id) {
                throw new LogicException(__('La sosta è inclusa in un abbonamento e non richiede un pagamento separato.'));
            }

            if ($stay->status !== ParkingStayStatus::Completed) {
                throw new LogicException(__('Registra prima l’uscita del veicolo.'));
            }

            if ($stay->payments()->where('status', GaragePaymentStatus::Paid->value)->exists()) {
                throw new LogicException(__('La sosta risulta già pagata.'));
            }

            $payment = GaragePayment::query()->create([
                'parking_id' => $stay->parking_id,
                'parking_stay_id' => $stay->id,
                'provider' => 'manual',
                'method' => $data['method'],
                'status' => GaragePaymentStatus::Paid->value,
                'amount' => $data['amount'],
                'currency' => $stay->currency,
                'recorded_by' => $operator->id,
                'paid_at' => Carbon::parse($data['paid_at'] ?? now()),
                'raw_data' => ['source' => 'operator'],
            ]);

            $stay->update(['payment_status' => GaragePaymentStatus::Paid->value]);

            return $payment;
        });
    }

    public function createStripePendingPayment(
        ParkingSubscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): GaragePayment {
        $existing = GaragePayment::query()
            ->where('parking_subscription_id', $subscription->id)
            ->where('provider', 'stripe')
            ->where('status', GaragePaymentStatus::Pending->value)
            ->whereDate('billing_period_start', $periodStart)
            ->whereDate('billing_period_end', $periodEnd)
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        return GaragePayment::query()->create([
            'parking_id' => $subscription->parking_id,
            'parking_subscription_id' => $subscription->id,
            'provider' => 'stripe',
            'method' => GaragePaymentMethod::Stripe->value,
            'status' => GaragePaymentStatus::Pending->value,
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'raw_data' => ['source' => 'stripe_checkout'],
        ]);
    }

    public function confirmStripePayment(
        GaragePayment $payment,
        int $actualAmountInCents,
        string $currency,
        array $providerData = [],
    ): GaragePayment {
        return DB::transaction(function () use ($payment, $actualAmountInCents, $currency, $providerData): GaragePayment {
            $payment = GaragePayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === GaragePaymentStatus::Paid) {
                return $payment;
            }

            if ($payment->provider !== 'stripe') {
                throw new LogicException('Only Stripe payments can be confirmed by this operation.');
            }

            if ((int) round((float) $payment->amount * 100) !== $actualAmountInCents
                || strtoupper($payment->currency) !== strtoupper($currency)) {
                $payment->update([
                    'status' => GaragePaymentStatus::Failed->value,
                    'raw_data' => [
                        ...($payment->raw_data ?? []),
                        'confirmation_error' => 'amount_or_currency_mismatch',
                        'provider_data' => $providerData,
                    ],
                ]);

                throw new LogicException('Stripe amount or currency does not match the expected payment.');
            }

            $payment->update([
                'status' => GaragePaymentStatus::Paid->value,
                'paid_at' => now(),
                'raw_data' => [
                    ...($payment->raw_data ?? []),
                    'provider_data' => $providerData,
                ],
            ]);

            if ($payment->subscription) {
                $this->syncSubscriptionPaidThrough($payment->subscription);
            }

            return $payment->refresh();
        });
    }

    public function reverse(GaragePayment $payment, string $reason, User $operator): GaragePayment
    {
        return DB::transaction(function () use ($payment, $reason, $operator): GaragePayment {
            $payment = GaragePayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== GaragePaymentStatus::Paid) {
                throw new LogicException(__('Si possono stornare soltanto pagamenti registrati come pagati.'));
            }

            if ($payment->provider === 'stripe' && ! config('demo.enabled')) {
                throw new LogicException(__('I pagamenti Stripe devono essere rimborsati da Stripe prima di aggiornare lo storico locale.'));
            }

            $payment->update([
                'status' => GaragePaymentStatus::Reversed->value,
                'reversed_at' => now(),
                'reversed_by' => $operator->id,
                'reversal_reason' => trim($reason),
            ]);

            if ($payment->subscription) {
                $this->syncSubscriptionPaidThrough($payment->subscription);
            }

            if ($payment->stay) {
                $hasPaidPayment = $payment->stay->payments()
                    ->where('status', GaragePaymentStatus::Paid->value)
                    ->exists();
                $payment->stay->update([
                    'payment_status' => $hasPaidPayment
                        ? GaragePaymentStatus::Paid->value
                        : 'unpaid',
                ]);
            }

            return $payment->refresh();
        });
    }

    private function syncSubscriptionPaidThrough(ParkingSubscription $subscription): void
    {
        $paidThrough = $subscription->payments()
            ->where('status', GaragePaymentStatus::Paid->value)
            ->max('billing_period_end');

        $subscription->update([
            'paid_through' => $paidThrough,
            'next_billing_on' => $paidThrough
                ? Carbon::parse($paidThrough)->addDay()->toDateString()
                : $subscription->starts_on->toDateString(),
        ]);
    }
}
