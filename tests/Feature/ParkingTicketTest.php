<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ticket preview supports A4 A6 labels and custom dimensions', function () {
    ['reservation' => $reservation] = invoicingScenario([
        'ticket_format' => 'a6',
        'ticket_width_mm' => 80,
        'ticket_height_mm' => 60,
        'ticket_show_logo' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('reservations.ticket', ['reservation' => $reservation, 'format' => 'a4']))
        ->assertOk()
        ->assertSee('--paper-width: 210mm', false)
        ->assertSee('--paper-height: 297mm', false);

    $this->actingAs($admin)
        ->get(route('reservations.ticket', [
            'reservation' => $reservation,
            'format' => 'label',
            'orientation' => 'landscape',
            'width' => 80,
            'height' => 60,
        ]))
        ->assertOk()
        ->assertSee('--paper-width: 60mm', false)
        ->assertSee('--paper-height: 80mm', false)
        ->assertSee('sodano-consulting-source.png');

    $this->actingAs($admin)
        ->get(route('reservations.ticket', [
            'reservation' => $reservation,
            'format' => 'custom',
            'orientation' => 'portrait',
            'width' => 100,
            'height' => 50,
        ]))
        ->assertOk()
        ->assertSee('--paper-width: 100mm', false)
        ->assertSee('--paper-height: 50mm', false)
        ->assertSee('value="100"', false)
        ->assertSee('value="50"', false);
});

test('recording checkout returns an automatic print URL only when configured', function () {
    ['reservation' => $reservation, 'settings' => $settings] = invoicingScenario([
        'ticket_auto_open_on_exit' => true,
    ]);
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->postJson(route('reservations.toggle-movement', $reservation), [
            'type' => 'exited',
            'value' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('print_url', route('reservations.ticket', [
            'reservation' => $reservation,
            'autoprint' => 1,
        ]));

    $settings->update(['ticket_auto_open_on_exit' => false]);

    $this->actingAs($staff)
        ->postJson(route('reservations.toggle-movement', $reservation), [
            'type' => 'exited',
            'value' => true,
        ])
        ->assertOk()
        ->assertJsonPath('print_url', null);
});

test('logo can be disabled on the ticket', function () {
    ['reservation' => $reservation, 'settings' => $settings] = invoicingScenario();
    $settings->update(['ticket_show_logo' => false]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('reservations.ticket', $reservation))
        ->assertOk()
        ->assertDontSee('sodano-consulting-source.png');
});

test('default ticket title and footer are translated', function () {
    ['reservation' => $reservation] = invoicingScenario([
        'ticket_title' => 'Ricevuta di ritiro veicolo',
        'ticket_footer' => 'Documento dimostrativo. Conservare fino al ritiro del veicolo.',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    app()->setLocale('nl');

    $this->actingAs($admin)
        ->get(route('reservations.ticket', $reservation))
        ->assertOk()
        ->assertSee('Voertuigafhaalbewijs')
        ->assertSee('Demodocument. Bewaar het tot het voertuig wordt opgehaald.')
        ->assertDontSee('Ricevuta di ritiro veicolo');
});
