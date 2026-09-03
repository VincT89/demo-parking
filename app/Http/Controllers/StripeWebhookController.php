<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use App\Models\Payment;
use App\Services\PaymentConfirmationService;
use Exception;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, PaymentConfirmationService $confirmation)
    {
        if (config('demo.enabled')) {
            return response()->json(['received' => true, 'demo' => true]);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('payments.stripe.webhook_secret')
            );
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $payment = Payment::where('provider', 'stripe')
                ->where('provider_session_id', $session->id)
                ->first();

            if ($payment) {
                $payment->update([
                    'provider_payment_id' => $session->payment_intent ?? null,
                ]);

                // amount_total is already in cents
                $actualPaidAmountInCents = (int) ($session->amount_total ?? 0);
                $currency = $session->currency ?? 'EUR';

                try {
                    $confirmation->confirm($payment, $actualPaidAmountInCents, $currency, $session->toArray());
                } catch (\LogicException $e) {
                    \Illuminate\Support\Facades\Log::warning('Stripe Webhook LogicException: ' . $e->getMessage());
                } catch (Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Stripe Webhook Error: ' . $e->getMessage());
                    return response()->json(['error' => 'Internal Server Error'], 500); // force retry
                }
            }
        } elseif ($event->type === 'payment_intent.succeeded') {
            $intent = $event->data->object;

            $payment = Payment::where('provider', 'stripe')
                ->where('provider_payment_id', $intent->id)
                ->first();

            if (!$payment && isset($intent->metadata->payment_id)) {
                $payment = Payment::where('provider', 'stripe')
                    ->where('id', $intent->metadata->payment_id)
                    ->first();
            }

            if ($payment) {
                $actualPaidAmountInCents = (int) ($intent->amount_received ?? 0);
                $currency = $intent->currency ?? 'EUR';

                try {
                    $confirmation->confirm($payment, $actualPaidAmountInCents, $currency, $intent->toArray());
                } catch (\LogicException $e) {
                    \Illuminate\Support\Facades\Log::warning('Stripe Webhook LogicException: ' . $e->getMessage());
                } catch (Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Stripe Webhook Error: ' . $e->getMessage());
                    return response()->json(['error' => 'Internal Server Error'], 500); // force retry
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
