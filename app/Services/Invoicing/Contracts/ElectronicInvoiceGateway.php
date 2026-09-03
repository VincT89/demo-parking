<?php

namespace App\Services\Invoicing\Contracts;

use App\Models\ElectronicInvoice;
use App\Models\ParkingSetting;
use App\Services\Invoicing\InvoiceGatewayResult;

interface ElectronicInvoiceGateway
{
    public function submit(ElectronicInvoice $invoice, ParkingSetting $settings): InvoiceGatewayResult;

    public function refresh(ElectronicInvoice $invoice, ParkingSetting $settings): InvoiceGatewayResult;
}
