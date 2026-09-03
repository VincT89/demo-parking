<?php

namespace App\Services\Invoicing;

use App\Models\ElectronicInvoice;
use App\Models\ParkingSetting;
use App\Services\Invoicing\Contracts\ElectronicInvoiceGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class ArubaGateway implements ElectronicInvoiceGateway
{
    public function submit(ElectronicInvoice $invoice, ParkingSetting $settings): InvoiceGatewayResult
    {
        try {
            $token = $this->authenticate($settings);
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->post($this->servicesBaseUrl($settings).'/services/invoice/upload', [
                    'dataFile' => base64_encode((string) $invoice->xml_payload),
                    'skipExtraSchema' => false,
                ]);

            if (! $response->successful()) {
                return InvoiceGatewayResult::failed($this->httpError($response), [
                    'environment' => $settings->invoice_mode,
                    'http_status' => $response->status(),
                ]);
            }

            $payload = $response->json();
            $errorCode = (string) ($payload['errorCode'] ?? '');

            if (! in_array($errorCode, ['', '0000'], true)) {
                return InvoiceGatewayResult::failed(
                    trim($errorCode.' '.($payload['errorDescription'] ?? __('Invio rifiutato da Aruba.'))),
                    ['environment' => $settings->invoice_mode, 'error_code' => $errorCode],
                );
            }

            return new InvoiceGatewayResult(
                success: true,
                status: 'processing',
                remoteId: $this->requestId($payload['errorDescription'] ?? null),
                remoteFilename: $payload['uploadFileName'] ?? null,
                metadata: [
                    'environment' => $settings->invoice_mode,
                    'method' => 'POST',
                    'path' => '/services/invoice/upload',
                    'response' => [
                        'errorCode' => $errorCode,
                        'errorDescription' => $payload['errorDescription'] ?? null,
                        'uploadFileName' => $payload['uploadFileName'] ?? null,
                    ],
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            return InvoiceGatewayResult::failed(__('Collegamento ad Aruba non riuscito. Verifica credenziali e ambiente.'));
        }
    }

    public function refresh(ElectronicInvoice $invoice, ParkingSetting $settings): InvoiceGatewayResult
    {
        if (blank($invoice->remote_filename)) {
            return InvoiceGatewayResult::failed(__('Nome file Aruba non disponibile.'));
        }

        try {
            $token = $this->authenticate($settings);
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->get($this->servicesBaseUrl($settings).'/services/invoice/out/getByFilename', [
                    'filename' => $invoice->remote_filename,
                    'includePdf' => false,
                ]);

            if (! $response->successful()) {
                return InvoiceGatewayResult::failed($this->httpError($response), [
                    'environment' => $settings->invoice_mode,
                    'http_status' => $response->status(),
                ]);
            }

            $payload = $response->json();
            $remoteStatus = (string) data_get($payload, 'invoices.0.status', 'Presa in carico');
            $statusDescription = data_get($payload, 'invoices.0.statusDescription');
            $status = $this->mapStatus($remoteStatus);

            return new InvoiceGatewayResult(
                success: true,
                status: $status,
                remoteId: (string) ($payload['id'] ?? $invoice->remote_id),
                remoteFilename: (string) ($payload['filename'] ?? $invoice->remote_filename),
                sdiId: isset($payload['idSdi']) ? (string) $payload['idSdi'] : $invoice->sdi_id,
                error: in_array($status, ['error', 'rejected', 'delivery_failed'], true)
                    ? ($statusDescription ?: $remoteStatus)
                    : null,
                metadata: [
                    'environment' => $settings->invoice_mode,
                    'method' => 'GET',
                    'path' => '/services/invoice/out/getByFilename',
                    'response' => [
                        'id' => $payload['id'] ?? null,
                        'filename' => $payload['filename'] ?? null,
                        'idSdi' => $payload['idSdi'] ?? null,
                        'status' => $remoteStatus,
                        'statusDescription' => $statusDescription,
                    ],
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            return InvoiceGatewayResult::failed(__('Aggiornamento stato Aruba non riuscito.'));
        }
    }

    private function authenticate(ParkingSetting $settings): string
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post($this->authBaseUrl($settings).'/auth/signin', [
                'grant_type' => 'password',
                'username' => $settings->aruba_username,
                'password' => $settings->aruba_password,
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new \RuntimeException('Aruba authentication failed with HTTP '.$response->status());
        }

        return (string) $response->json('access_token');
    }

    private function authBaseUrl(ParkingSetting $settings): string
    {
        return $settings->invoice_mode === 'aruba_production'
            ? 'https://auth.fatturazioneelettronica.aruba.it'
            : 'https://demoauth.fatturazioneelettronica.aruba.it';
    }

    private function servicesBaseUrl(ParkingSetting $settings): string
    {
        return $settings->invoice_mode === 'aruba_production'
            ? 'https://ws.fatturazioneelettronica.aruba.it'
            : 'https://demows.fatturazioneelettronica.aruba.it';
    }

    private function mapStatus(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'presa in carico' => 'processing',
            'errore elaborazione' => 'error',
            'inviata' => 'submitted',
            'scartata' => 'rejected',
            'non consegnata', 'recapito impossibile' => 'delivery_failed',
            'consegnata' => 'delivered',
            'accettata' => 'accepted',
            'rifiutata' => 'rejected',
            'decorrenza termini' => 'expired_terms',
            default => 'processing',
        };
    }

    private function requestId(?string $description): ?string
    {
        if ($description && preg_match('/-\s*([a-f0-9]{16,})$/i', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function httpError(Response $response): string
    {
        $description = $response->json('errorDescription');

        return $description
            ? (string) $description
            : __('Aruba ha risposto con codice HTTP :status.', ['status' => $response->status()]);
    }
}
