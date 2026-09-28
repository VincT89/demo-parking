<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class PublicPaymentController extends Controller
{
    public function show(string $externalId)
    {
        $reservation = Reservation::where('external_id', $externalId)->firstOrFail();

        abort_if($reservation->status !== ReservationStatus::Pending, 404);
        abort_if($reservation->expires_at && $reservation->expires_at->isPast(), 410);

        $settings = app(\App\Services\PaymentConfiguration::class)->forManagement();
        return view('booking.payment', [
            'reservation' => $reservation,
            'stripePublicKey' => ($settings['stripe']['public_key'] ?? null),
            'paypalClientId' => ($settings['paypal']['client_id'] ?? null),
            'paypalCurrency' => ($settings['paypal']['currency'] ?? 'EUR'),
        ]);
    }

    public function confirmOnsite(string $externalId)
    {
        $reservation = Reservation::where('external_id', $externalId)->firstOrFail();

        if ($reservation->status !== ReservationStatus::Pending) {
            abort(404);
        }

        if ($reservation->expires_at && $reservation->expires_at->isPast()) {
            abort(410);
        }

        DB::transaction(function () use ($reservation) {
            $reservation->payments()->firstOrCreate(
                [
                    'provider' => 'onsite',
                    'status' => PaymentStatus::Pending->value,
                ],
                [
                    'amount' => $reservation->price,
                    'currency' => 'EUR',
                    'raw_data' => [
                        'source' => config('demo.enabled') ? 'demo_onsite_confirmation' : 'temporary_onsite_payment',
                        'demo' => config('demo.enabled'),
                    ],
                ]
            );

            $reservation->update([
                'status' => ReservationStatus::Confirmed->value,
                'expires_at' => null,
            ]);
        });

        return redirect()->route('public.booking.success', $reservation->external_id);
    }
}
