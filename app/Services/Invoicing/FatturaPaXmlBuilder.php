<?php

namespace App\Services\Invoicing;

use App\Models\ElectronicInvoice;
use App\Models\ParkingSetting;
use DOMDocument;
use DOMElement;

class FatturaPaXmlBuilder
{
    public function build(ElectronicInvoice $invoice, ParkingSetting $settings): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElementNS(
            'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2',
            'p:FatturaElettronica'
        );
        $root->setAttribute('versione', 'FPR12');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $document->appendChild($root);

        $header = $this->node($document, $root, 'FatturaElettronicaHeader');
        $transmission = $this->node($document, $header, 'DatiTrasmissione');
        $transmitter = $this->node($document, $transmission, 'IdTrasmittente');
        $this->value($document, $transmitter, 'IdPaese', 'IT');
        $this->value($document, $transmitter, 'IdCodice', '01879020517');
        $this->value($document, $transmission, 'ProgressivoInvio', $this->transmissionSequence($invoice));
        $this->value($document, $transmission, 'FormatoTrasmissione', 'FPR12');
        $this->value($document, $transmission, 'CodiceDestinatario', $this->recipientCode($invoice));
        $contacts = $this->node($document, $transmission, 'ContattiTrasmittente');
        $this->value($document, $contacts, 'Telefono', '05750505');
        $this->value($document, $contacts, 'Email', 'info@arubapec.it');

        if ($this->recipientCode($invoice) === '0000000' && filled($invoice->customer_pec)) {
            $this->value($document, $transmission, 'PECDestinatario', $invoice->customer_pec);
        }

        $seller = $this->node($document, $header, 'CedentePrestatore');
        $sellerRegistry = $this->node($document, $seller, 'DatiAnagrafici');
        $sellerVat = $this->node($document, $sellerRegistry, 'IdFiscaleIVA');
        $this->value($document, $sellerVat, 'IdPaese', strtoupper($settings->issuer_vat_country));
        $this->value($document, $sellerVat, 'IdCodice', $settings->issuer_vat_number);
        if (filled($settings->issuer_fiscal_code)) {
            $this->value($document, $sellerRegistry, 'CodiceFiscale', $settings->issuer_fiscal_code);
        }
        $sellerName = $this->node($document, $sellerRegistry, 'Anagrafica');
        $this->value($document, $sellerName, 'Denominazione', $this->limit($settings->issuer_business_name, 80));
        $this->value($document, $sellerRegistry, 'RegimeFiscale', $settings->issuer_tax_regime);
        $this->address($document, $seller, $settings->issuer_address, $settings->issuer_postal_code, $settings->issuer_city, $settings->issuer_province, $settings->issuer_country);

        $buyer = $this->node($document, $header, 'CessionarioCommittente');
        $buyerRegistry = $this->node($document, $buyer, 'DatiAnagrafici');
        if (filled($invoice->customer_vat_number)) {
            $buyerVat = $this->node($document, $buyerRegistry, 'IdFiscaleIVA');
            $this->value($document, $buyerVat, 'IdPaese', strtoupper($invoice->customer_vat_country));
            $this->value($document, $buyerVat, 'IdCodice', $invoice->customer_vat_number);
        }
        if (filled($invoice->customer_fiscal_code)) {
            $this->value($document, $buyerRegistry, 'CodiceFiscale', strtoupper($invoice->customer_fiscal_code));
        }
        $buyerName = $this->node($document, $buyerRegistry, 'Anagrafica');
        $this->value($document, $buyerName, 'Denominazione', $this->limit($invoice->customer_name, 80));
        $this->address($document, $buyer, $invoice->customer_address, $invoice->customer_postal_code, $invoice->customer_city, $invoice->customer_province, $invoice->customer_country);

        $body = $this->node($document, $root, 'FatturaElettronicaBody');
        $general = $this->node($document, $body, 'DatiGenerali');
        $generalDocument = $this->node($document, $general, 'DatiGeneraliDocumento');
        $this->value($document, $generalDocument, 'TipoDocumento', 'TD01');
        $this->value($document, $generalDocument, 'Divisa', 'EUR');
        $this->value($document, $generalDocument, 'Data', $invoice->created_at->toDateString());
        $this->value($document, $generalDocument, 'Numero', $this->limit($invoice->number, 20));
        $this->value($document, $generalDocument, 'ImportoTotaleDocumento', $this->money($invoice->total_amount));

        $goods = $this->node($document, $body, 'DatiBeniServizi');
        $line = $this->node($document, $goods, 'DettaglioLinee');
        $this->value($document, $line, 'NumeroLinea', '1');
        $this->value($document, $line, 'Descrizione', $this->limit('Servizio di parcheggio - prenotazione '.$invoice->reservation->external_id, 1000));
        $this->value($document, $line, 'Quantita', '1.00');
        $this->value($document, $line, 'PrezzoUnitario', $this->money($invoice->taxable_amount));
        $this->value($document, $line, 'PrezzoTotale', $this->money($invoice->taxable_amount));
        $this->value($document, $line, 'AliquotaIVA', $this->money($invoice->vat_rate));

        $summary = $this->node($document, $goods, 'DatiRiepilogo');
        $this->value($document, $summary, 'AliquotaIVA', $this->money($invoice->vat_rate));
        $this->value($document, $summary, 'ImponibileImporto', $this->money($invoice->taxable_amount));
        $this->value($document, $summary, 'Imposta', $this->money($invoice->vat_amount));
        $this->value($document, $summary, 'EsigibilitaIVA', 'I');

        $payment = $this->node($document, $body, 'DatiPagamento');
        $this->value($document, $payment, 'CondizioniPagamento', 'TP02');
        $paymentDetail = $this->node($document, $payment, 'DettaglioPagamento');
        $this->value($document, $paymentDetail, 'ModalitaPagamento', 'MP01');
        $this->value($document, $paymentDetail, 'DataScadenzaPagamento', $invoice->created_at->toDateString());
        $this->value($document, $paymentDetail, 'ImportoPagamento', $this->money($invoice->total_amount));

        return (string) $document->saveXML();
    }

    private function address(DOMDocument $document, DOMElement $parent, ?string $street, ?string $postalCode, ?string $city, ?string $province, ?string $country): void
    {
        $address = $this->node($document, $parent, 'Sede');
        $this->value($document, $address, 'Indirizzo', $this->limit((string) $street, 60));
        $this->value($document, $address, 'CAP', $this->limit((string) $postalCode, 5));
        $this->value($document, $address, 'Comune', $this->limit((string) $city, 60));
        if (filled($province)) {
            $this->value($document, $address, 'Provincia', strtoupper((string) $province));
        }
        $this->value($document, $address, 'Nazione', strtoupper((string) $country));
    }

    private function node(DOMDocument $document, DOMElement $parent, string $name): DOMElement
    {
        $node = $document->createElement($name);
        $parent->appendChild($node);

        return $node;
    }

    private function value(DOMDocument $document, DOMElement $parent, string $name, string|int|float|null $value): DOMElement
    {
        $node = $document->createElement($name);
        $node->appendChild($document->createTextNode((string) $value));
        $parent->appendChild($node);

        return $node;
    }

    private function recipientCode(ElectronicInvoice $invoice): string
    {
        if (strtoupper($invoice->customer_country) !== 'IT') {
            return 'XXXXXXX';
        }

        return strtoupper($invoice->customer_recipient_code ?: '0000000');
    }

    private function transmissionSequence(ElectronicInvoice $invoice): string
    {
        return substr((string) $invoice->year.str_pad((string) $invoice->sequence, 6, '0', STR_PAD_LEFT), -10);
    }

    private function money(string|float|int $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function limit(?string $value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }
}
