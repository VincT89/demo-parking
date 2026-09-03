<?php

namespace App\Http\Controllers;

use App\Models\ElectronicInvoice;
use App\Models\Parking;
use App\Models\ParkingSetting;
use App\Models\Reservation;
use App\Services\Invoicing\ElectronicInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ElectronicInvoiceController extends Controller
{
    public function __construct(private ElectronicInvoiceService $service) {}

    public function index(Request $request)
    {
        $query = ElectronicInvoice::query()->with(['reservation', 'parking'])->latest();

        if ($request->filled('parking_id')) {
            $query->where('parking_id', $request->integer('parking_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return view('invoices.index', [
            'invoices' => $query->paginate(20)->withQueryString(),
            'parkings' => Parking::query()->orderBy('name')->get(),
        ]);
    }

    public function create(Reservation $reservation)
    {
        $reservation->load(['parking', 'electronicInvoice']);

        if ($reservation->electronicInvoice) {
            return redirect()->route('invoices.show', $reservation->electronicInvoice);
        }

        $settings = ParkingSetting::query()->firstOrCreate(['parking_id' => $reservation->parking_id]);

        return view('invoices.create', compact('reservation', 'settings'));
    }

    public function store(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'customer_type' => ['required', Rule::in(['person', 'company'])],
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_vat_country' => ['required', 'alpha', 'size:2'],
            'customer_vat_number' => ['nullable', 'string', 'max:28'],
            'customer_fiscal_code' => ['nullable', 'string', 'max:16'],
            'customer_recipient_code' => ['nullable', 'alpha_num', 'size:7'],
            'customer_pec' => ['nullable', 'email:rfc', 'max:255'],
            'customer_address' => ['required', 'string', 'max:60'],
            'customer_postal_code' => ['required', 'string', 'max:10'],
            'customer_city' => ['required', 'string', 'max:60'],
            'customer_province' => ['nullable', 'alpha', 'size:2'],
            'customer_country' => ['required', 'alpha', 'size:2'],
        ]);

        foreach (['customer_vat_country', 'customer_fiscal_code', 'customer_recipient_code', 'customer_province', 'customer_country'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = strtoupper(trim((string) $validated[$field]));
            }
        }

        $invoice = $this->service->create($reservation, $validated);

        return redirect()->route('invoices.show', $invoice)->with('success', __('Bozza fattura creata.'));
    }

    public function show(ElectronicInvoice $invoice)
    {
        $invoice->load(['reservation.parkingProduct', 'reservation.parkingListing.platform', 'parking.settings']);
        $settings = $invoice->parking->settings ?? ParkingSetting::query()->firstOrCreate(['parking_id' => $invoice->parking_id]);

        return view('invoices.show', compact('invoice', 'settings'));
    }

    public function submit(ElectronicInvoice $invoice)
    {
        $invoice = $this->service->submit($invoice);

        return back()->with(
            $invoice->status === 'error' ? 'error' : 'success',
            $invoice->status === 'error' ? $invoice->last_error : __('Fattura inviata: chiamata API completata.'),
        );
    }

    public function refresh(ElectronicInvoice $invoice)
    {
        $invoice = $this->service->refresh($invoice);

        return back()->with(
            $invoice->status === 'error' ? 'error' : 'success',
            $invoice->status === 'error' ? $invoice->last_error : __('Stato fattura aggiornato.'),
        );
    }

    public function simulate(Request $request, ElectronicInvoice $invoice)
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['delivered', 'rejected', 'delivery_failed'])],
        ]);

        $this->service->simulateOutcome($invoice, $validated['outcome']);

        return back()->with('success', __('Notifica SdI simulata.'));
    }

    public function downloadXml(ElectronicInvoice $invoice)
    {
        abort_if(blank($invoice->xml_payload), 404, __('XML disponibile solo dopo un invio Aruba.'));

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $invoice->number).'.xml';

        return response($invoice->xml_payload, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
