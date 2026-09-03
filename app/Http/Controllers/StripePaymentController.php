<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PricingService;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripePaymentController extends Controller
{
    public function checkout(string $externalId, PricingService $pricing)
    {
        abort_if(
            config('demo.enabled'),
            403,
            __('I pagamenti reali sono disabilitati nella demo.')
        );

        $reservation = Reservation::where('external_id', $externalId)->firstOrFail();

        abort_if($reservation->status !== ReservationStatus::Pending, 404);
        abort_if($reservation->expires_at && $reservation->expires_at->isPast(), 410);

        Stripe::setApiKey(config('payments.stripe.secret_key'));

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'status' => PaymentStatus::Pending->value,
            'amount' => $reservation->price,
            'currency' => config('payments.currency', 'EUR'),
        ]);

        $session = Session::create([
            'mode' => 'payment',
            'client_reference_id' => (string) $reservation->external_id,
            'customer_email' => $reservation->customer_email,
            'metadata' => [
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'payment_id' => $payment->id,
                ],
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower(config('payments.currency', 'EUR')),
                    'unit_amount' => $pricing->amountForReservation($reservation),
                    'product_data' => [
                        'name' => 'Prenotazione parcheggio #' . $reservation->external_id,
                    ],
                ],
            ]],
            'success_url' => route('public.booking.success', $reservation->external_id) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('public.booking.payment', $reservation->external_id),
        ]);

        $payment->update([
            'provider_session_id' => $session->id,
            'raw_data' => $session->toArray(),
        ]);

        return redirect($session->url);
    }
}
