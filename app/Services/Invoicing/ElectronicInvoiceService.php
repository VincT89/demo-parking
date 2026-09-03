<?php

namespace App\Services\Invoicing;

use App\Models\ElectronicInvoice;
use App\Models\ParkingSetting;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ElectronicInvoiceService
{
    public function __construct(
        private SimulatedArubaGateway $simulator,
        private ArubaGateway $aruba,
        private FatturaPaXmlBuilder $xmlBuilder,
    ) {}

    public function create(Reservation $reservation, array $customerData): ElectronicInvoice
    {
        if ($reservation->electronicInvoice) {
            return $reservation->electronicInvoice;
        }

        if ((float) $reservation->price <= 0) {
            throw ValidationException::withMessages([
                'price' => __('La prenotazione deve avere un importo maggiore di zero.'),
            ]);
        }

        return DB::transaction(function () use ($reservation, $customerData) {
            $settings = ParkingSetting::query()
                ->where('parking_id', $reservation->parking_id)
                ->lockForUpdate()
                ->firstOrCreate(['parking_id' => $reservation->parking_id]);

            $sequence = $settings->next_invoice_number;
            $year = now()->year;
            $settings->increment('next_invoice_number');

            [$taxable, $vat, $total] = $this->amounts(
                (float) $reservation->price,
                (float) $settings->default_vat_rate,
                $settings->prices_include_vat,
            );

            $prefix = strtoupper(trim($settings->invoice_prefix ?: 'DEMO'));
            $number = sprintf('%s-%d/%04d', $prefix, $year, $sequence);

            return ElectronicInvoice::query()->create([
                'reservation_id' => $reservation->id,
                'parking_id' => $reservation->parking_id,
                'number' => $number,
                'sequence' => $sequence,
                'year' => $year,
                'provider_mode' => $settings->invoice_mode,
                'status' => 'draft',
                ...$customerData,
                'taxable_amount' => $taxable,
                'vat_rate' => $settings->default_vat_rate,
                'vat_amount' => $vat,
                'total_amount' => $total,
                'status_history' => [[
                    'at' => now()->toIso8601String(),
                    'status' => 'draft',
                    'source' => 'application',
                    'message' => 'Documento creato',
                ]],
            ]);
        });
    }

    public function submit(ElectronicInvoice $invoice): ElectronicInvoice
    {
        if (! in_array($invoice->status, ['draft', 'error', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'invoice' => __('Questa fattura è già stata trasmessa.'),
            ]);
        }

        $settings = $this->settings($invoice);
        $invoice->provider_mode = $settings->invoice_mode;

        if ($settings->usesAruba()) {
            $this->validateRealConfiguration($settings, $invoice);
            $invoice->loadMissing('reservation');
            $invoice->xml_payload = $this->xmlBuilder->build($invoice, $settings);
        } else {
            $invoice->xml_payload = null;
        }

        $invoice->save();
        $result = $this->gateway($settings)->submit($invoice, $settings);

        return $this->applyResult($invoice, $result, 'submit');
    }

    public function refresh(ElectronicInvoice $invoice): ElectronicInvoice
    {
        $settings = $this->settings($invoice);
        $result = $this->gateway($settings)->refresh($invoice, $settings);

        return $this->applyResult($invoice, $result, 'refresh');
    }

    public function simulateOutcome(ElectronicInvoice $invoice, string $outcome): ElectronicInvoice
    {
        $settings = $this->settings($invoice);

        if (! $settings->isSimulator()) {
            throw ValidationException::withMessages([
                'simulation' => __('Gli esiti manuali sono disponibili solo nel simulatore locale.'),
            ]);
        }

        if (! in_array($invoice->status, ['processing', 'submitted'], true)) {
            throw ValidationException::withMessages([
                'simulation' => __('Prima invia la fattura al simulatore.'),
            ]);
        }

        $status = match ($outcome) {
            'delivered' => 'delivered',
            'rejected' => 'rejected',
            'delivery_failed' => 'delivery_failed',
            default => throw ValidationException::withMessages([
                'simulation' => __('Esito simulato non valido.'),
            ]),
        };

        $result = new InvoiceGatewayResult(
            success: true,
            status: $status,
            remoteId: $invoice->remote_id,
            remoteFilename: $invoice->remote_filename,
            sdiId: $status === 'delivered' ? 'SIM-SDI-'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT) : null,
            error: $status === 'rejected'
                ? 'Scarto simulato: anagrafica destinatario non valida.'
                : ($status === 'delivery_failed' ? 'Mancata consegna simulata.' : null),
            metadata: [
                'environment' => 'local_simulator',
                'method' => 'POST',
                'path' => '/services/sdisimulator/notificationOut',
                'response' => [
                    'notificationType' => match ($status) {
                        'delivered' => 'RC',
                        'rejected' => 'NS',
                        default => 'MC',
                    },
                ],
            ],
        );

        return $this->applyResult($invoice, $result, 'simulated_notification');
    }

    private function settings(ElectronicInvoice $invoice): ParkingSetting
    {
        return ParkingSetting::query()->firstOrCreate(['parking_id' => $invoice->parking_id]);
    }

    private function gateway(ParkingSetting $settings): Contracts\ElectronicInvoiceGateway
    {
        return $settings->isSimulator() ? $this->simulator : $this->aruba;
    }

    private function applyResult(ElectronicInvoice $invoice, InvoiceGatewayResult $result, string $source): ElectronicInvoice
    {
        $history = $invoice->status_history ?? [];
        $history[] = [
            'at' => now()->toIso8601String(),
            'status' => $result->status,
            'source' => $source,
            'success' => $result->success,
            'message' => $result->error,
            'api' => $result->metadata,
        ];

        $invoice->fill([
            'status' => $result->success ? $result->status : 'error',
            'remote_id' => $result->remoteId ?? $invoice->remote_id,
            'remote_filename' => $result->remoteFilename ?? $invoice->remote_filename,
            'sdi_id' => $result->sdiId ?? $invoice->sdi_id,
            'last_error' => $result->error,
            'status_history' => $history,
            'submitted_at' => $source === 'submit' && $result->success ? now() : $invoice->submitted_at,
            'delivered_at' => in_array($result->status, ['delivered', 'accepted'], true) ? now() : $invoice->delivered_at,
            'last_status_check_at' => now(),
        ])->save();

        return $invoice->refresh();
    }

    private function amounts(float $reservationPrice, float $vatRate, bool $includesVat): array
    {
        $priceCents = (int) round($reservationPrice * 100);
        $rate = $vatRate / 100;

        if ($includesVat) {
            $totalCents = $priceCents;
            $taxableCents = (int) round($totalCents / (1 + $rate));
            $vatCents = $totalCents - $taxableCents;
        } else {
            $taxableCents = $priceCents;
            $vatCents = (int) round($taxableCents * $rate);
            $totalCents = $taxableCents + $vatCents;
        }

        return [
            number_format($taxableCents / 100, 2, '.', ''),
            number_format($vatCents / 100, 2, '.', ''),
            number_format($totalCents / 100, 2, '.', ''),
        ];
    }

    private function validateRealConfiguration(ParkingSetting $settings, ElectronicInvoice $invoice): void
    {
        $validator = Validator::make([
            ...$settings->only([
                'aruba_username',
                'aruba_password',
                'issuer_business_name',
                'issuer_vat_country',
                'issuer_vat_number',
                'issuer_tax_regime',
                'issuer_address',
                'issuer_postal_code',
                'issuer_city',
                'issuer_country',
            ]),
            'customer_vat_number' => $invoice->customer_vat_number,
            'customer_fiscal_code' => $invoice->customer_fiscal_code,
        ], [
            'aruba_username' => ['required', 'string'],
            'aruba_password' => ['required', 'string'],
            'issuer_business_name' => ['required', 'string', 'max:80'],
            'issuer_vat_country' => ['required', 'alpha', 'size:2'],
            'issuer_vat_number' => ['required', 'regex:/^\d{11}$/'],
            'issuer_tax_regime' => ['required', 'regex:/^RF\d{2}$/'],
            'issuer_address' => ['required', 'string', 'max:60'],
            'issuer_postal_code' => ['required', 'regex:/^\d{5}$/'],
            'issuer_city' => ['required', 'string', 'max:60'],
            'issuer_country' => ['required', 'alpha', 'size:2'],
            'customer_vat_number' => ['nullable', 'required_without:customer_fiscal_code', 'max:28'],
            'customer_fiscal_code' => ['nullable', 'required_without:customer_vat_number', 'max:16'],
        ], [
            'customer_vat_number.required_without' => __('Per Aruba occorre la partita IVA o il codice fiscale del cliente.'),
            'customer_fiscal_code.required_without' => __('Per Aruba occorre la partita IVA o il codice fiscale del cliente.'),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
