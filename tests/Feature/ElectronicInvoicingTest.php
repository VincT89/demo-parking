<?php

use App\Models\ElectronicInvoice;
use App\Models\Parking;
use App\Models\ParkingListing;
use App\Models\ParkingProduct;
use App\Models\ParkingSetting;
use App\Models\Platform;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('local simulator creates and completes an electronic invoice without HTTP calls', function () {
    config()->set('demo.enabled', true);
    Http::fake();
    ['reservation' => $reservation] = invoicingScenario();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('invoices.store', $reservation), invoiceCustomerData())
        ->assertRedirect();

    $invoice = ElectronicInvoice::query()->firstOrFail();
    expect($invoice->number)->toBe('DEMO-'.now()->year.'/0001')
        ->and($invoice->taxable_amount)->toBe('50.00')
        ->and($invoice->vat_amount)->toBe('11.00')
        ->and($invoice->total_amount)->toBe('61.00')
        ->and($invoice->xml_payload)->toBeNull();

    $this->actingAs($admin)
        ->post(route('invoices.submit', $invoice))
        ->assertSessionHas('success');

    $invoice->refresh();
    expect($invoice->status)->toBe('processing')
        ->and($invoice->remote_id)->toStartWith('SIM-')
        ->and($invoice->remote_filename)->toStartWith('SIM_');

    $this->actingAs($admin)
        ->post(route('invoices.simulate', $invoice), ['outcome' => 'delivered'])
        ->assertSessionHas('success');

    $invoice->refresh();
    expect($invoice->status)->toBe('delivered')
        ->and($invoice->sdi_id)->toStartWith('SIM-SDI-')
        ->and($invoice->status_history)->toHaveCount(3);

    app()->setLocale('en_GB');
    $this->actingAs($admin)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Delivered')
        ->assertSee('Document created')
        ->assertDontSee('Documento creato');

    Http::assertNothingSent();
});

test('Aruba demo submission uses official endpoints and FatturaPA transmitter data', function () {
    config()->set('demo.enabled', true);
    ['reservation' => $reservation] = invoicingScenario([
        'invoice_mode' => 'aruba_demo',
        'aruba_username' => 'ARUBA-DEMO-USER',
        'aruba_password' => 'demo-secret',
        'issuer_business_name' => 'Example Parking Srl',
        'issuer_vat_country' => 'IT',
        'issuer_vat_number' => '12345678901',
        'issuer_fiscal_code' => '12345678901',
        'issuer_tax_regime' => 'RF01',
        'issuer_address' => 'Via Esempio 1',
        'issuer_postal_code' => '70100',
        'issuer_city' => 'Bari',
        'issuer_province' => 'BA',
        'issuer_country' => 'IT',
    ]);

    Http::fake([
        'https://demoauth.fatturazioneelettronica.aruba.it/auth/signin' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 1800,
        ]),
        'https://demows.fatturazioneelettronica.aruba.it/services/invoice/upload' => Http::response([
            'errorCode' => '0000',
            'errorDescription' => 'Operazione effettuata - 521e052902be7b879d41e0fd586f0e21',
            'uploadFileName' => 'IT12345678901_test.xml.p7m',
        ]),
    ]);

    $service = app(\App\Services\Invoicing\ElectronicInvoiceService::class);
    $invoice = $service->create($reservation, invoiceCustomerData());
    $invoice = $service->submit($invoice);

    expect($invoice->status)->toBe('processing')
        ->and($invoice->remote_filename)->toBe('IT12345678901_test.xml.p7m')
        ->and($invoice->xml_payload)->toContain('<IdCodice>01879020517</IdCodice>')
        ->and($invoice->xml_payload)->toContain('<FormatoTrasmissione>FPR12</FormatoTrasmissione>')
        ->and($invoice->xml_payload)->toContain('<Numero>DEMO-'.now()->year.'/0001</Numero>');

    Http::assertSent(fn ($request) =>
        $request->url() === 'https://demoauth.fatturazioneelettronica.aruba.it/auth/signin'
        && $request['grant_type'] === 'password'
        && $request['username'] === 'ARUBA-DEMO-USER'
    );
    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://demows.fatturazioneelettronica.aruba.it/services/invoice/upload') {
            return false;
        }

        $xml = base64_decode($request['dataFile'], true);

        return $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['skipExtraSchema'] === false
            && str_contains($xml, '<IdCodice>01879020517</IdCodice>');
    });
});

test('Aruba credentials are encrypted and never rendered back into the settings form', function () {
    ['parking' => $parking, 'settings' => $settings] = invoicingScenario([
        'aruba_username' => 'private-user',
        'aruba_password' => 'private-password',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $raw = \Illuminate\Support\Facades\DB::table('parking_settings')->where('id', $settings->id)->first();
    expect($raw->aruba_username)->not->toContain('private-user')
        ->and($raw->aruba_password)->not->toContain('private-password');

    $this->actingAs($admin)
        ->get(route('operational-settings.edit', ['parking_id' => $parking->id]))
        ->assertOk()
        ->assertDontSee('private-user')
        ->assertDontSee('private-password')
        ->assertSee('Configurato');
});

test('staff cannot change operational invoicing settings', function () {
    ['parking' => $parking] = invoicingScenario();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->put(route('operational-settings.update', $parking), [])
        ->assertForbidden();
});

test('translated default ticket copy stays localizable after settings are saved', function () {
    ['parking' => $parking, 'settings' => $settings] = invoicingScenario([
        'ticket_title' => 'Ricevuta di ritiro veicolo',
        'ticket_footer' => 'Documento dimostrativo. Conservare fino al ritiro del veicolo.',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    app()->setLocale('nl');

    $this->actingAs($admin)
        ->get(route('operational-settings.edit', ['parking_id' => $parking->id]))
        ->assertOk()
        ->assertSee('Voertuigafhaalbewijs')
        ->assertDontSee('Ricevuta di ritiro veicolo');

    $settings->refresh();
    $payload = $settings->only([
        'invoice_mode',
        'issuer_business_name',
        'issuer_vat_country',
        'issuer_vat_number',
        'issuer_fiscal_code',
        'issuer_tax_regime',
        'issuer_address',
        'issuer_postal_code',
        'issuer_city',
        'issuer_province',
        'issuer_country',
        'invoice_prefix',
        'next_invoice_number',
        'default_vat_rate',
        'prices_include_vat',
        'ticket_format',
        'ticket_orientation',
        'ticket_width_mm',
        'ticket_height_mm',
        'ticket_auto_open_on_exit',
        'ticket_show_logo',
    ]);
    $payload['ticket_title'] = 'Voertuigafhaalbewijs';
    $payload['ticket_footer'] = 'Demodocument. Bewaar het tot het voertuig wordt opgehaald.';

    $this->actingAs($admin)
        ->put(route('operational-settings.update', $parking), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($settings->refresh()->ticket_title)->toBe('Ricevuta di ritiro veicolo')
        ->and($settings->ticket_footer)->toBe('Documento dimostrativo. Conservare fino al ritiro del veicolo.');
});
