<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use LogicException;

class PaymentConfirmationService
{
    public function confirm(Payment $payment, int $actualPaidAmountInCents, string $actualCurrency, array $rawData = []): Reservation
    {
        return DB::transaction(function () use ($payment, $actualPaidAmountInCents, $actualCurrency, $rawData) {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservation = Reservation::query()
                ->whereKey($payment->reservation_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PaymentStatus::Paid->value) {
                return $reservation;
            }

            $expectedCents = (int) round($payment->amount * 100);
            $existingRawData = $payment->raw_data ?? [];
            $mergedRawData = array_merge($existingRawData, [
                'event_' . time() => $rawData
            ]);

            if (strtoupper($payment->currency) !== strtoupper($actualCurrency)) {
                $payment->update([
                    'status' => PaymentStatus::Failed->value,
                    'raw_data' => array_merge($existingRawData, [
                        'error' => 'Currency mismatch',
                        'expected' => $payment->currency,
                        'actual' => $actualCurrency,
                        'event_' . time() => $rawData
                    ]),
                ]);
                throw new LogicException('Payment currency does not match reservation currency.');
            }

            if ($expectedCents !== $actualPaidAmountInCents) {
                $payment->update([
                    'status' => PaymentStatus::Failed->value,
                    'raw_data' => array_merge($existingRawData, [
                        'error' => 'Amount mismatch',
                        'expected' => $expectedCents,
                        'actual' => $actualPaidAmountInCents,
                        'event_' . time() => $rawData
                    ]),
                ]);
                throw new LogicException('Payment amount does not match reservation amount.');
            }

            if ($reservation->status->value !== ReservationStatus::Pending->value) {
                throw new LogicException('Reservation is not pending.');
            }

            if ($reservation->expires_at && $reservation->expires_at->isPast()) {
                $payment->update([
                    'status' => PaymentStatus::Expired->value,
                    'raw_data' => $mergedRawData,
                ]);

                throw new LogicException('Reservation payment window expired.');
            }

            $payment->update([
                'status' => PaymentStatus::Paid->value,
                'raw_data' => $mergedRawData,
                'paid_at' => now(),
            ]);

            $reservation->update([
                'status' => ReservationStatus::Confirmed->value,
                'expires_at' => null,
            ]);

            return $reservation;
        });
    }
}
