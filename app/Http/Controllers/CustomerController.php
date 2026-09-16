<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\GaragePayment;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\CustomerHistoryService;
use App\Services\CustomerRegistryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    private function validateInput(Request $request, array $rules): array
    {
        $invalid = __('Il valore del campo :attribute non è valido.');
        $attributes = [];
        foreach ([
            'name' => 'Nome o ragione sociale', 'email' => 'Email', 'phone' => 'Telefono',
            'type' => 'Tipo cliente', 'state' => 'Stato', 'search' => 'Cerca cliente',
            'fiscal_code' => 'Codice fiscale', 'vat_number' => 'Partita IVA', 'vat_country' => 'Paese IVA',
            'recipient_code' => 'Codice destinatario', 'pec' => 'PEC destinatario',
            'address' => 'Indirizzo', 'postal_code' => 'CAP', 'city' => 'Comune', 'province' => 'Provincia',
            'country' => 'Nazione', 'notes' => 'Note interne', 'license_plates' => 'Targhe associate',
            'kind' => 'Tipo operazione', 'record_id' => 'Operazione', 'is_active' => 'Stato',
            'date_from' => 'Dal', 'date_to' => 'Al', 'confirm_duplicate' => 'Possibili duplicati',
        ] as $key => $label) {
            $attributes[$key] = __($label);
        }
        return $request->validate($rules, [
            'required' => __('Compila il campo :attribute.'),
            'email' => $invalid, 'string' => $invalid, 'integer' => $invalid,
            'alpha' => $invalid, 'alpha_num' => $invalid, 'boolean' => $invalid,
            'in' => $invalid, 'min' => $invalid, 'date' => $invalid,
            'max' => __('Il campo :attribute supera il limite di :max caratteri.'),
            'size' => __('Il campo :attribute deve contenere :size caratteri.'),
            'after_or_equal' => __('La data finale deve essere uguale o successiva alla data iniziale.'),
        ], $attributes);
    }

    public function index(Request $request)
    {
        $data = $this->validateInput($request, [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['person', 'company'])],
            'state' => ['nullable', Rule::in(['active', 'archived', 'all'])],
        ]);
        $query = Customer::query()->search($data['search'] ?? '')->with('vehicles');
        if (($data['state'] ?? 'active') !== 'all') {
            $query->where('is_active', ($data['state'] ?? 'active') === 'active');
        }
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        return view('customers.index', ['customers' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()]);
    }

    public function lookup(Request $request)
    {
        $data = $this->validateInput($request, ['search' => ['required', 'string', 'min:2', 'max:255']]);
        return response()->json(Customer::query()->where('is_active', true)->search($data['search'])
            ->with('vehicles')->orderBy('name')->limit(15)->get()->map(fn ($customer) => [
                'id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email,
                'phone' => $customer->phone, 'plates' => $customer->vehicles->pluck('license_plate'),
            ]))->header('Cache-Control', 'private, no-store');
    }

    public function create(CustomerRegistryService $registry)
    {
        $customer = new Customer(['type' => 'person', 'country' => 'IT', 'vat_country' => 'IT', 'is_active' => true]);
        return view('customers.form', ['customer' => $customer, 'duplicates' => $registry->duplicates(session()->getOldInput())]);
    }

    public function store(Request $request, CustomerRegistryService $registry)
    {
        $customer = $registry->save($this->validated($request));
        return redirect()->route('customers.show', $customer)->with('success', __('Cliente creato correttamente.'));
    }

    public function edit(Customer $customer, CustomerRegistryService $registry)
    {
        $customer->load('vehicles');
        return view('customers.form', ['customer' => $customer, 'duplicates' => $registry->duplicates(session()->getOldInput(), $customer)]);
    }

    public function update(Request $request, Customer $customer, CustomerRegistryService $registry)
    {
        $registry->save($this->validated($request), $customer);
        return redirect()->route('customers.show', $customer)->with('success', __('Anagrafica cliente aggiornata.'));
    }

    public function show(Request $request, Customer $customer, CustomerHistoryService $history)
    {
        $data = $this->validateInput($request, [
            'kind' => ['nullable', Rule::in(['reservation', 'subscription', 'stay', 'payment', 'invoice'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
        ]);
        $query = $history->query($customer);
        if (! empty($data['kind'])) {
            $query->whereIn('kind', $data['kind'] === 'payment' ? ['payment', 'garage_payment'] : [$data['kind']]);
        }
        if (! empty($data['date_from'])) {
            $query->whereDate('event_at', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $query->whereDate('event_at', '<=', $data['date_to']);
        }
        $activities = $query->orderByDesc('event_at')->orderBy('kind')->orderByDesc('id')->paginate(20)->withQueryString();
        // Resolve only this page of payment owners; no per-row database queries.
        $payments = Payment::query()->whereIn('id', $activities->where('kind', 'payment')->pluck('id'))->get()->keyBy('id');
        $garagePayments = GaragePayment::query()->whereIn('id', $activities->where('kind', 'garage_payment')->pluck('id'))->get()->keyBy('id');
        $activities->getCollection()->transform(function ($row) use ($payments, $garagePayments) {
            $row->label = CustomerHistoryService::label($row->kind);
            $row->status_label = CustomerHistoryService::status($row->kind, $row->status);
            $row->url = match ($row->kind) {
                'reservation' => route('reservations.show', $row->id),
                'subscription' => route('garage.subscriptions.show', $row->id),
                'stay' => route('garage.stays.show', $row->id),
                'invoice' => route('invoices.show', $row->id),
                'payment' => route('reservations.show', $payments[$row->id]->reservation_id),
                'garage_payment' => $garagePayments[$row->id]->parking_stay_id
                    ? route('garage.stays.show', $garagePayments[$row->id]->parking_stay_id)
                    : route('garage.subscriptions.show', $garagePayments[$row->id]->parking_subscription_id),
            };
            return $row;
        });
        $customer->load('vehicles');
        return view('customers.show', compact('customer', 'activities'));
    }

    public function status(Request $request, Customer $customer)
    {
        $data = $this->validateInput($request, ['is_active' => ['required', 'boolean']]);
        $customer->update($data);
        return back()->with('success', $customer->is_active ? __('Cliente riattivato.') : __('Cliente archiviato. Lo storico resta consultabile.'));
    }

    public function records(Request $request, Customer $customer)
    {
        $data = $this->validateInput($request, [
            'kind' => ['nullable', Rule::in(['reservation', 'subscription', 'stay'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $kind = $data['kind'] ?? 'reservation';
        $search = trim($data['search'] ?? ($customer->email ?: $customer->name));
        $model = match ($kind) {
            'reservation' => Reservation::class, 'subscription' => ParkingSubscription::class, 'stay' => ParkingStay::class,
        };
        $records = $model::query()->whereNull('customer_id')->with('parking')
            ->where(function ($q) use ($search) {
                if ($search === '') {
                    $q->whereRaw('1 = 0');
                    return;
                }
                $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('customer_email', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%')->orWhere('license_plate', 'like', '%'.$search.'%');
            })->latest($kind === 'subscription' ? 'starts_on' : 'starts_at')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('customers.records', compact('customer', 'kind', 'search', 'records'));
    }

    public function link(Request $request, Customer $customer, CustomerRegistryService $registry)
    {
        $data = $this->validateInput($request, [
            'kind' => ['required', Rule::in(['reservation', 'subscription', 'stay'])],
            'record_id' => ['required', 'integer', 'min:1'],
        ]);
        $registry->link($customer, $data['kind'], $data['record_id']);
        return back()->with('success', __('Operazione collegata. Anche i relativi pagamenti sono visibili nello storico.'));
    }

    private function validated(Request $request): array
    {
        return $this->validateInput($request, [
            'type' => ['required', Rule::in(['person', 'company'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fiscal_code' => ['nullable', 'string', 'max:16'],
            'vat_number' => ['nullable', 'string', 'max:28'],
            'vat_country' => ['required', 'alpha', 'size:2'],
            'recipient_code' => ['nullable', 'alpha_num', 'size:7'],
            'pec' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:60'],
            'province' => ['nullable', 'alpha', 'size:2'],
            'country' => ['required', 'alpha', 'size:2'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'license_plates' => ['nullable', 'string', 'max:1500'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ]);
    }
}
