<?php

namespace App\Services\Invoicing;

final readonly class InvoiceGatewayResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $remoteId = null,
        public ?string $remoteFilename = null,
        public ?string $sdiId = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    public static function failed(string $error, array $metadata = []): self
    {
        return new self(false, 'error', error: $error, metadata: $metadata);
    }
}
