<?php

namespace App\Http\Controllers;

use App\Enums\GaragePaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\GaragePayment;
use App\Models\ParkingSubscription;
use App\Services\GaragePaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class SubscriptionStripeController extends Controller
{
    public function checkout(ParkingSubscription $subscription, GaragePaymentService $payments)
    {
        abort_unless(
            $subscription->payment_mode === 'stripe'
                && $subscription->status === SubscriptionStatus::Active,
            404,
        );

        $periodStart = Carbon::parse($subscription->next_billing_on ?? $subscription->starts_on)->startOfDay();
        $periodEnd = $periodStart->copy()->addMonthNoOverflow()->subDay();
        $payment = $payments->createStripePendingPayment($subscription, $periodStart, $periodEnd);

        if (config('demo.enabled')) {
            $payment->update([
                'provider_session_id' => 'demo_stripe_'.$payment->id,
                'raw_data' => [
                    ...($payment->raw_data ?? []),
                    'demo' => true,
                    'simulated_api' => 'stripe.checkout.sessions.create',
                ],
            ]);

            return redirect()->route('garage.stripe.simulation', $payment);
        }

        Stripe::setApiKey(config('payments.stripe.secret_key'));

        $session = Session::create([
            'mode' => 'subscription',
            'client_reference_id' => (string) $subscription->uuid,
            'customer_email' => $subscription->customer_email,
            'metadata' => [
                'garage_payment_id' => $payment->id,
                'parking_subscription_id' => $subscription->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'parking_subscription_id' => $subscription->id,
                ],
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($subscription->currency),
                    'unit_amount' => (int) round((float) $subscription->price * 100),
                    'recurring' => ['interval' => 'month'],
                    'product_data' => [
                        'name' => __('Abbonamento parcheggio').' '.$subscription->reference,
                    ],
                ],
            ]],
            'success_url' => route('public.subscription-payment.result', $subscription->uuid).'?stripe=success',
            'cancel_url' => route('public.subscription-payment.result', $subscription->uuid).'?stripe=cancelled',
        ]);

        $payment->update([
            'provider_session_id' => $session->id,
            'raw_data' => $session->toArray(),
        ]);

        return redirect()->away($session->url);
    }

    public function publicResult(Request $request, string $uuid)
    {
        $subscription = ParkingSubscription::query()
            ->where('uuid', $uuid)
            ->with('latestPayment')
            ->firstOrFail();

        return view('garage.payments.public-result', [
            'subscription' => $subscription,
            'cancelled' => $request->boolean('cancelled') || $request->string('stripe')->toString() === 'cancelled',
        ]);
    }

    public function simulation(GaragePayment $payment)
    {
        abort_unless(config('demo.enabled') && $payment->provider === 'stripe', 404);
        $payment->load('subscription');

        return view('garage.payments.stripe-simulation', compact('payment'));
    }

    public function simulate(Request $request, GaragePayment $payment, GaragePaymentService $payments)
    {
        abort_unless(config('demo.enabled') && $payment->provider === 'stripe', 404);

        $validated = $request->validate([
            'outcome' => ['required', 'in:success,failure'],
        ]);

        if ($validated['outcome'] === 'success') {
            $payments->confirmStripePayment(
                $payment,
                (int) round((float) $payment->amount * 100),
                $payment->currency,
                [
                    'id' => $payment->provider_session_id,
                    'object' => 'checkout.session',
                    'mode' => 'subscription',
                    'livemode' => false,
                    'simulated' => true,
                ],
            );

            $message = __('Pagamento Stripe simulato con esito positivo.');
        } else {
            $payment->update([
                'status' => GaragePaymentStatus::Failed->value,
                'raw_data' => [
                    ...($payment->raw_data ?? []),
                    'simulated_outcome' => 'failure',
                ],
            ]);

            $message = __('Pagamento Stripe simulato come non riuscito.');
        }

        return redirect()
            ->route('garage.subscriptions.show', $payment->parking_subscription_id)
            ->with('success', $message);
    }
}
