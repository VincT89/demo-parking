<?php

namespace App\Http\Controllers;

use App\Enums\GaragePaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\GarageRate;
use App\Models\Parking;
use App\Models\ParkingSubscription;
use App\Services\ParkingSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class ParkingSubscriptionController extends Controller
{
    public function __construct(private ParkingSubscriptionService $service) {}

    public function index(Request $request)
    {
        $query = ParkingSubscription::query()
            ->with(['parking', 'parkingProduct', 'latestPayment']);

        if ($request->filled('parking_id')) {
            $query->where('parking_id', $request->integer('parking_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(fn ($builder) => $builder
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhere('license_plate', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%"));
        }

        return view('garage.subscriptions.index', [
            'subscriptions' => $query->latest('starts_on')->paginate(20)->withQueryString(),
            'parkings' => Parking::query()->active()->orderBy('name')->get(),
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }

    public function create()
    {
        $parkings = Parking::query()
            ->active()
            ->with([
                'products' => fn ($query) => $query->active()->orderBy('sort_order'),
                'garageRates' => fn ($query) => $query
                    ->active()
                    ->where('kind', 'subscription')
                    ->orderBy('sort_order'),
            ])
            ->orderBy('name')
            ->get();

        return view('garage.subscriptions.create', compact('parkings'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parking_id' => ['required', 'exists:parkings,id'],
            'parking_product_id' => ['required', 'exists:parking_products,id'],
            'garage_rate_id' => ['required', 'exists:garage_rates,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'license_plate' => ['required', 'string', 'max:32'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reserved_spots' => ['required', 'integer', 'min:1', 'max:50'],
            'payment_mode' => ['required', Rule::in(['manual', 'stripe'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $subscription = $this->service->create($validated, $request->user());
        } catch (LogicException $exception) {
            return back()->withInput()->withErrors(['subscription' => $exception->getMessage()]);
        }

        return redirect()
            ->route('garage.subscriptions.show', $subscription)
            ->with('success', __('Abbonamento creato correttamente.'));
    }

    public function show(ParkingSubscription $subscription)
    {
        $subscription->load([
            'parking',
            'parkingProduct',
            'rate',
            'creator',
            'stays' => fn ($query) => $query->latest('starts_at')->limit(10),
            'payments' => fn ($query) => $query->with(['recordedBy', 'reversedBy'])->latest('paid_at'),
        ]);

        return view('garage.subscriptions.show', [
            'subscription' => $subscription,
            'statuses' => SubscriptionStatus::cases(),
            'paymentMethods' => array_values(array_filter(
                GaragePaymentMethod::cases(),
                fn (GaragePaymentMethod $method) => $method !== GaragePaymentMethod::Stripe,
            )),
        ]);
    }

    public function edit(ParkingSubscription $subscription)
    {
        $subscription->load(['parking', 'parkingProduct', 'rate']);

        return view('garage.subscriptions.edit', [
            'subscription' => $subscription,
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }

    public function update(Request $request, ParkingSubscription $subscription)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'license_plate' => ['required', 'string', 'max:32'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'reserved_spots' => ['required', 'integer', 'min:1', 'max:50'],
            'payment_mode' => ['required', Rule::in(['manual', 'stripe'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->update($subscription, $validated);
        } catch (LogicException $exception) {
            return back()->withInput()->withErrors(['subscription' => $exception->getMessage()]);
        }

        return redirect()
            ->route('garage.subscriptions.show', $subscription)
            ->with('success', __('Abbonamento aggiornato correttamente.'));
    }
}
