<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Services\PaymentConfirmationService;
use Exception;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function handle(Request $request, PaymentConfirmationService $confirmation)
    {
        if (config('demo.enabled')) {
            return response()->json(['received' => true, 'demo' => true]);
        }

        // Usiamo l'approccio di validazione sicura Server-to-Server (Zero-Trust sul payload del webhook).
        $payload = $request->all();

        $eventType = $payload['event_type'] ?? null;
        $resource = $payload['resource'] ?? [];

        if ($eventType === 'CHECKOUT.ORDER.APPROVED' || $eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            
            // For ORDER.APPROVED the id is the order id
            // For CAPTURE.COMPLETED we need to trace back to order id or use custom id
            $orderId = null;
            $actualPaidAmount = 0.0;

            if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
                $orderId = $resource['id'] ?? null;
                // If it's just approved, we might need to trigger a server-side capture here 
                // if the client didn't do it. But usually, CAPTURE.COMPLETED is what we want.
            } elseif ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
                // Find order id from links or custom_id if provided
                // A reliable way is looking for the order id in the parent references if we store capture_id
                // Since our controller only saves provider_order_id initially, we must find the payment by order ID
                $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
                $actualPaidAmount = (float) ($resource['amount']['value'] ?? 0);
            }

            if ($orderId) {
                $payment = Payment::where('provider', 'paypal')
                    ->where('provider_order_id', $orderId)
                    ->first();

                if ($payment) {
                    try {
                        // Only process CAPTURE.COMPLETED
                        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
                            
                            // SERVER-SIDE VALIDATION: ZERO TRUST
                            // Non fidarci mai degli importi nel payload del webhook che potrebbe non essere verificato
                            // Chiediamo l'assoluta verità direttamente all'API di PayPal
                            $paypalService = app(\App\Services\PayPalService::class);
                            $orderData = $paypalService->getOrder($orderId);

                            if (($orderData['status'] ?? null) === 'COMPLETED') {
                                $trustedAmount = (float) ($orderData['purchase_units'][0]['amount']['value'] ?? 0);
                                $trustedCurrency = $orderData['purchase_units'][0]['amount']['currency_code'] ?? 'EUR';
                                
                                $actualPaidAmountInCents = (int) round($trustedAmount * 100);
                                $confirmation->confirm($payment, $actualPaidAmountInCents, $trustedCurrency, $payload);
                            }
                        }
                    } catch (\LogicException $e) {
                        Log::warning('PayPal Webhook LogicException: ' . $e->getMessage());
                    } catch (Exception $e) {
                        Log::error('PayPal Webhook Error: ' . $e->getMessage());
                        return response()->json(['error' => 'Internal Server Error'], 500); // force retry
                    }
                } else {
                    Log::warning('PayPal webhook: payment not found', ['order_id' => $orderId]);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
