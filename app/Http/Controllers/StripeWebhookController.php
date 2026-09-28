<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use App\Models\Payment;
use App\Models\GaragePayment;
use App\Models\ParkingSubscription;
use App\Services\PaymentConfirmationService;
use App\Services\GaragePaymentService;
use App\Enums\GaragePaymentMethod;
use App\Enums\GaragePaymentStatus;
use App\Enums\SubscriptionStatus;
use Carbon\Carbon;
use Exception;

class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        PaymentConfirmationService $confirmation,
        GaragePaymentService $garageConfirmation,
        ?string $managementKey = null,
    )
    {
        if (config('demo.enabled')) {
            return response()->json(['received' => true, 'demo' => true]);
        }

        $configuration = app(\App\Services\PaymentConfiguration::class);
        $management = $configuration->webhookManagement($managementKey);
        $settings = $configuration->forManagement($management);
        abort_if(blank($settings['stripe']['webhook_secret'] ?? null), 503);
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $settings['stripe']['webhook_secret']
            );
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // Resolve the signed event only inside the management owning this endpoint.
        $object = $event->data->object;
        $parking = null;
        $paymentQuery = Payment::where('provider', 'stripe')->whereHas('reservation.parking', fn ($q) => $q->where('management_id', $management->id));
        $subscriptionQuery = ParkingSubscription::whereHas('parking', fn ($q) => $q->where('management_id', $management->id));
        if ($event->type === 'checkout.session.completed') {
            if (isset($object->payment_status) && ! in_array($object->payment_status, ['paid', 'no_payment_required'], true)) {
                return response()->json(['received' => true]);
            }
            if (isset($object->metadata->garage_payment_id)) {
                $payment = GaragePayment::where('provider', 'stripe')
                    ->whereHas('parking', fn ($q) => $q->where('management_id', $management->id))
                    ->where('provider_session_id', $object->id)->find($object->metadata->garage_payment_id);
                $parking = $payment?->parking;
            } else {
                $parking = $paymentQuery->where('provider_session_id', $object->id)->first()?->reservation?->parking;
            }
        } elseif ($event->type === 'payment_intent.succeeded') {
            $payment = (clone $paymentQuery)->where('provider_payment_id', $object->id)->first();
            if (! $payment && isset($object->metadata->payment_id)) {
                $payment = $paymentQuery->find($object->metadata->payment_id);
            }
            $parking = $payment?->reservation?->parking;
        } elseif (in_array($event->type, ['invoice.paid', 'invoice.payment_failed'], true)) {
            $id = is_string($object->subscription ?? null) ? $object->subscription : ($object->subscription->id ?? null);
            $parking = $id ? $subscriptionQuery->where('stripe_subscription_id', $id)->first()?->parking : null;
        } elseif (in_array($event->type, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $parking = $subscriptionQuery->where('stripe_subscription_id', $object->id)->first()?->parking;
        }
        if (! $parking) {
            return response()->json(['received' => true]);
        }

        return app(\App\Tenancy\ParkingContext::class)->run($parking, fn () => $this->process($event, $confirmation, $garageConfirmation));
    }

    private function process($event, PaymentConfirmationService $confirmation, GaragePaymentService $garageConfirmation)
    {
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            if (isset($session->metadata->garage_payment_id)) {
                $garagePayment = GaragePayment::query()->find($session->metadata->garage_payment_id);

                if ($garagePayment) {
                    $garagePayment->update([
                        'provider_payment_id' => $session->invoice ?? $session->payment_intent ?? null,
                        'provider_subscription_id' => $session->subscription ?? null,
                    ]);

                    if ($garagePayment->subscription && isset($session->subscription)) {
                        $garagePayment->subscription->update([
                            'stripe_subscription_id' => $session->subscription,
                        ]);
                    }

                    try {
                        $garageConfirmation->confirmStripePayment(
                            $garagePayment,
                            (int) ($session->amount_total ?? 0),
                            $session->currency ?? 'EUR',
                            $session->toArray(),
                        );
                    } catch (\LogicException $e) {
                        \Illuminate\Support\Facades\Log::warning('Garage Stripe webhook rejected: '.$e->getMessage());
                    }

                    return response()->json(['received' => true]);
                }
            }

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
        } elseif ($event->type === 'invoice.paid') {
            $invoice = $event->data->object;
            $providerSubscriptionId = is_string($invoice->subscription ?? null)
                ? $invoice->subscription
                : ($invoice->subscription->id ?? null);
            $subscription = $providerSubscriptionId
                ? ParkingSubscription::query()->where('stripe_subscription_id', $providerSubscriptionId)->first()
                : null;

            if ($subscription) {
                $period = $invoice->lines->data[0]->period ?? null;
                $payment = GaragePayment::query()->firstOrCreate(
                    [
                        'provider' => 'stripe',
                        'provider_payment_id' => $invoice->id,
                    ],
                    [
                        'parking_id' => $subscription->parking_id,
                        'parking_subscription_id' => $subscription->id,
                        'method' => GaragePaymentMethod::Stripe->value,
                        'status' => GaragePaymentStatus::Pending->value,
                        'amount' => ((int) ($invoice->amount_paid ?? 0)) / 100,
                        'currency' => strtoupper($invoice->currency ?? $subscription->currency),
                        'billing_period_start' => isset($period->start) ? Carbon::createFromTimestamp($period->start)->toDateString() : null,
                        'billing_period_end' => isset($period->end) ? Carbon::createFromTimestamp($period->end)->subDay()->toDateString() : null,
                        'provider_subscription_id' => $providerSubscriptionId,
                        'raw_data' => $invoice->toArray(),
                    ],
                );

                if ($payment->status === GaragePaymentStatus::Pending) {
                    try {
                        $garageConfirmation->confirmStripePayment(
                            $payment,
                            (int) ($invoice->amount_paid ?? 0),
                            $invoice->currency ?? $subscription->currency,
                            $invoice->toArray(),
                        );
                    } catch (\LogicException $e) {
                        \Illuminate\Support\Facades\Log::warning('Garage Stripe invoice rejected: '.$e->getMessage());
                    }
                }
            }
        } elseif ($event->type === 'invoice.payment_failed') {
            $invoice = $event->data->object;
            $providerSubscriptionId = is_string($invoice->subscription ?? null)
                ? $invoice->subscription
                : ($invoice->subscription->id ?? null);
            $subscription = $providerSubscriptionId
                ? ParkingSubscription::query()->where('stripe_subscription_id', $providerSubscriptionId)->first()
                : null;

            if ($subscription) {
                GaragePayment::query()->firstOrCreate(
                    ['provider' => 'stripe', 'provider_payment_id' => $invoice->id],
                    [
                        'parking_id' => $subscription->parking_id,
                        'parking_subscription_id' => $subscription->id,
                        'method' => GaragePaymentMethod::Stripe->value,
                        'status' => GaragePaymentStatus::Failed->value,
                        'amount' => ((int) ($invoice->amount_due ?? 0)) / 100,
                        'currency' => strtoupper($invoice->currency ?? $subscription->currency),
                        'provider_subscription_id' => $providerSubscriptionId,
                        'raw_data' => $invoice->toArray(),
                    ],
                );
            }
        } elseif (in_array($event->type, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $stripeSubscription = $event->data->object;
            $subscription = ParkingSubscription::query()
                ->where('stripe_subscription_id', $stripeSubscription->id)
                ->first();

            if ($subscription) {
                $subscription->update([
                    'status' => match ($stripeSubscription->status) {
                        'active', 'trialing' => SubscriptionStatus::Active->value,
                        'canceled' => SubscriptionStatus::Cancelled->value,
                        default => SubscriptionStatus::Suspended->value,
                    },
                ]);
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
