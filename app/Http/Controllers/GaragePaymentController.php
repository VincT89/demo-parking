<?php

namespace App\Http\Controllers;

use App\Enums\GaragePaymentMethod;
use App\Models\GaragePayment;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Services\GaragePaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class GaragePaymentController extends Controller
{
    public function __construct(private GaragePaymentService $service) {}

    public function storeForSubscription(Request $request, ParkingSubscription $subscription)
    {
        $validated = $request->validate([
            'method' => [
                'required',
                Rule::enum(GaragePaymentMethod::class),
                Rule::notIn([GaragePaymentMethod::Stripe->value]),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', 'date'],
            'billing_period_start' => ['required', 'date'],
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],
        ]);

        try {
            $this->service->recordSubscriptionPayment($subscription, $validated, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', __('Pagamento registrato correttamente.'));
    }

    public function storeForStay(Request $request, ParkingStay $stay)
    {
        $validated = $request->validate([
            'method' => [
                'required',
                Rule::enum(GaragePaymentMethod::class),
                Rule::notIn([GaragePaymentMethod::Stripe->value]),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'paid_at' => ['required', 'date'],
        ]);

        try {
            $this->service->recordStayPayment($stay, $validated, $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', __('Pagamento registrato correttamente.'));
    }

    public function reverse(Request $request, GaragePayment $payment)
    {
        $validated = $request->validate([
            'reversal_reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->service->reverse($payment, $validated['reversal_reason'], $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('success', __('Pagamento stornato. Lo storico è stato conservato.'));
    }
}
