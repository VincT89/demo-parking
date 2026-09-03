<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Payment;
use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use App\Services\PayPalService;
use App\Services\PaymentConfirmationService;

class PayPalPaymentController extends Controller
{
    public function createOrder(string $externalId, PayPalService $paypal)
    {
        abort_if(
            config('demo.enabled'),
            403,
            __('I pagamenti reali sono disabilitati nella demo.')
        );

        $reservation = Reservation::where('external_id', $externalId)->firstOrFail();

        abort_if($reservation->status !== ReservationStatus::Pending, 404);
        abort_if($reservation->expires_at && $reservation->expires_at->isPast(), 410);

        $order = $paypal->createOrder($reservation);

        Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'paypal',
            'status' => PaymentStatus::Pending->value,
            'amount' => $reservation->price,
            'currency' => config('payments.paypal.currency', 'EUR'),
            'provider_order_id' => $order['id'],
            'raw_data' => $order,
        ]);

        return response()->json([
            'id' => $order['id'],
        ]);
    }

    public function capture(Request $request, string $externalId, PayPalService $paypal, PaymentConfirmationService $confirmation)
    {
        abort_if(
            config('demo.enabled'),
            403,
            __('I pagamenti reali sono disabilitati nella demo.')
        );

        $orderId = $request->string('order_id');

        $payment = Payment::with('reservation')
            ->where('provider', 'paypal')
            ->where('provider_order_id', $orderId)
            ->firstOrFail();

        abort_if($payment->reservation->external_id !== $externalId, 404);

        \Illuminate\Support\Facades\Log::info('PayPal capture request received', [
            'external_id' => $externalId,
            'order_id' => $request->input('order_id'),
            'payload' => $request->all(),
        ]);

        $capture = $paypal->captureOrder($orderId);

        if (($capture['status'] ?? null) !== 'COMPLETED') {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'raw_data' => $capture,
            ]);

            return response()->json(['message' => 'Payment not completed.'], 422);
        }

        // Extract amount and currency from capture
        $actualPaidAmountInCents = 0;
        $actualCurrency = 'EUR';
        if (isset($capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'])) {
            $actualPaidAmountInCents = (int) round((float)$capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] * 100);
            $actualCurrency = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? 'EUR';
        }

        $reservation = $confirmation->confirm($payment, $actualPaidAmountInCents, $actualCurrency, $capture);

        return response()->json([
            'redirect_url' => route('public.booking.success', $reservation->external_id),
        ]);
    }
}
