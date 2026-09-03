<?php

namespace App\Services\Invoicing;

use App\Models\ElectronicInvoice;
use App\Models\ParkingSetting;
use App\Services\Invoicing\Contracts\ElectronicInvoiceGateway;
use Illuminate\Support\Str;

class SimulatedArubaGateway implements ElectronicInvoiceGateway
{
    public function submit(ElectronicInvoice $invoice, ParkingSetting $settings): InvoiceGatewayResult
    {
        $this->simulateLatency();

        return new InvoiceGatewayResult(
            success: true,
            status: 'processing',
            remoteId: 'SIM-'.strtoupper(Str::random(12)),
            remoteFilename: sprintf('SIM_%d_%04d.xml', $invoice->year, $invoice->sequence),
            metadata: [
                'environment' => 'local_simulator',
                'method' => 'POST',
                'path' => '/services/invoice/upload',
                'response' => [
                    'errorCode' => '0000',
                    'errorDescription' => 'Operazione simulata: documento preso in carico localmente',
                ],
            ],
        );
    }

    public function refresh(ElectronicInvoice $invoice, ParkingSetting $settings): InvoiceGatewayResult
    {
        $this->simulateLatency();

        return new InvoiceGatewayResult(
            success: true,
            status: $invoice->status,
            remoteId: $invoice->remote_id,
            remoteFilename: $invoice->remote_filename,
            sdiId: $invoice->sdi_id,
            metadata: [
                'environment' => 'local_simulator',
                'method' => 'GET',
                'path' => '/services/invoice/out/getByFilename',
                'response' => ['status' => $invoice->status],
            ],
        );
    }

    private function simulateLatency(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $milliseconds = max(0, min(2000, (int) config('demo.api_latency_ms', 0)));

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
