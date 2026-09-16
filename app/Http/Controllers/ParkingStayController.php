<?php

namespace App\Http\Controllers;

use App\Enums\GaragePaymentMethod;
use App\Enums\ParkingStayStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Parking;
use App\Models\ParkingSetting;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Services\ParkingStayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class ParkingStayController extends Controller
{
    public function __construct(private ParkingStayService $service) {}

    public function index(Request $request)
    {
        $query = ParkingStay::query()->with(['parking', 'parkingProduct', 'subscription']);

        if ($request->filled('parking_id')) {
            $query->where('parking_id', $request->integer('parking_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(fn ($builder) => $builder
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('license_plate', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%"));
        }

        return view('garage.stays.index', [
            'stays' => $query->latest('starts_at')->paginate(20)->withQueryString(),
            'parkings' => Parking::query()->active()->orderBy('name')->get(),
            'statuses' => ParkingStayStatus::cases(),
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
                    ->where('kind', 'walk_in')
                    ->orderBy('sort_order'),
            ])
            ->orderBy('name')
            ->get();

        $subscriptions = ParkingSubscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->with(['parking', 'parkingProduct'])
            ->orderBy('customer_name')
            ->get();

        return view('garage.stays.create', compact('parkings', 'subscriptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'parking_id' => ['required', 'exists:parkings,id'],
            'parking_product_id' => ['nullable', 'exists:parking_products,id'],
            'garage_rate_id' => ['nullable', 'exists:garage_rates,id', 'required_without:parking_subscription_id'],
            'parking_subscription_id' => ['nullable', 'exists:parking_subscriptions,id', 'required_without:garage_rate_id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'license_plate' => ['nullable', 'string', 'max:32', 'required_without:parking_subscription_id'],
            'starts_at' => ['required', 'date'],
            'expected_ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $stay = $this->service->checkIn($validated, $request->user());
        } catch (LogicException $exception) {
            return back()->withInput()->withErrors(['stay' => $exception->getMessage()]);
        }

        return redirect()
            ->route('garage.stays.show', $stay)
            ->with('success', __('Ingresso registrato correttamente.'));
    }

    public function show(ParkingStay $stay)
    {
        $stay->load([
            'parking',
            'parkingProduct',
            'rate',
            'subscription',
            'creator',
            'closedBy',
            'payments' => fn ($query) => $query->with(['recordedBy', 'reversedBy'])->latest('paid_at'),
        ]);

        return view('garage.stays.show', [
            'stay' => $stay,
            'paymentMethods' => array_values(array_filter(
                GaragePaymentMethod::cases(),
                fn (GaragePaymentMethod $method) => $method !== GaragePaymentMethod::Stripe,
            )),
        ]);
    }

    public function checkOut(Request $request, ParkingStay $stay)
    {
        $validated = $request->validate([
            'ended_at' => ['required', 'date', 'after:'.$stay->starts_at->toDateTimeString()],
        ]);

        try {
            $stay = $this->service->checkOut($stay, Carbon::parse($validated['ended_at']), $request->user());
        } catch (LogicException $exception) {
            return back()->withErrors(['stay' => $exception->getMessage()]);
        }

        $settings = ParkingSetting::query()->firstOrCreate(['parking_id' => $stay->parking_id]);

        if ($settings->ticket_auto_open_on_exit) {
            return redirect()->route('garage.stays.ticket', [
                'stay' => $stay,
                'autoprint' => 1,
            ]);
        }

        return back()->with('success', __('Uscita registrata e importo ricalcolato.'));
    }
}
