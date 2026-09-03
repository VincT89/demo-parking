<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Payment;
use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use App\Services\PaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // This test simulates the flow by creating a pending reservation manually
        // because setting up the whole public booking store requires active parkings, products, and platforms.
    }

    protected function createReservation(array $attributes = [])
    {
        // Setup minimal foreign keys needed by the DB schema
        $platform = \App\Models\Platform::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'active' => true]);
        $parking = \App\Models\Parking::firstOrCreate(['code' => 'TEST'], ['name' => 'Test', 'active' => true, 'capacity_mode' => 'global', 'total_spots' => 100]);
        $product = \App\Models\ParkingProduct::firstOrCreate(['parking_id' => $parking->id, 'code' => 'PROD'], ['name' => 'Product', 'active' => true, 'price' => 10, 'is_active' => true, 'capacity' => 100]);
        $listing = \App\Models\ParkingListing::firstOrCreate(['parking_id' => $parking->id, 'platform_id' => $platform->id], ['active' => true]);

        return Reservation::create(array_merge([
            'parking_id' => $parking->id,
            'parking_product_id' => $product->id,
            'parking_listing_id' => $listing->id,
            'external_id' => 'TEST-' . uniqid(),
            'customer_name' => 'Test',
            'customer_email' => 'test@test.com',
            'customer_phone' => '123',
            'license_plate' => 'AA111BB',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'spots' => 1,
            'price' => 100.00,
            'status' => ReservationStatus::Pending->value,
            'expires_at' => now()->addMinutes(15),
        ], $attributes));
    }

    public function test_payment_page_requires_pending_status()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Confirmed->value,
            'external_id' => 'TEST-1234'
        ]);

        $response = $this->get(route('public.booking.payment', $reservation->external_id));

        $response->assertStatus(404);
    }

    public function test_payment_page_shows_for_pending_status()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Pending->value,
            'expires_at' => now()->addMinutes(15),
            'external_id' => 'TEST-5678'
        ]);

        $response = $this->get(route('public.booking.payment', $reservation->external_id));

        $response->assertOk();
        $response->assertSee('TEST-5678');
        $response->assertSee('Completa la prenotazione demo');
    }

    public function test_success_page_requires_confirmed_status()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Pending->value,
            'external_id' => 'TEST-9999'
        ]);

        $response = $this->get(route('public.booking.success', $reservation->external_id));

        $response->assertStatus(404);
    }

    public function test_success_page_shows_for_confirmed_status()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Confirmed->value,
            'external_id' => 'TEST-1111'
        ]);

        $response = $this->get(route('public.booking.success', $reservation->external_id));

        $response->assertOk();
    }

    public function test_payment_confirmation_service_confirms_reservation()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Pending->value,
            'expires_at' => now()->addMinutes(15),
            'price' => 100.00
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'status' => PaymentStatus::Pending->value,
            'amount' => 100.00,
            'currency' => 'EUR',
            'provider_session_id' => 'cs_test_123',
        ]);

        $service = new PaymentConfirmationService();
        
        $confirmed = $service->confirm($payment, 10000, 'EUR', ['test' => true]);

        $this->assertEquals(ReservationStatus::Confirmed->value, $confirmed->status->value);
        $this->assertNull($confirmed->expires_at);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Paid->value, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertIsArray($payment->raw_data);
        $this->assertNotEmpty($payment->raw_data);
    }

    public function test_payment_after_expiration_fails()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Pending->value,
            'expires_at' => now()->subMinutes(5), // expired
            'price' => 100.00
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'status' => PaymentStatus::Pending->value,
            'amount' => 100.00,
            'currency' => 'EUR',
            'provider_session_id' => 'cs_test_expired',
        ]);

        $service = new PaymentConfirmationService();
        
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Reservation payment window expired.');

        $service->confirm($payment, 10000, 'EUR', []);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Expired->value, $payment->status);
    }

    public function test_payment_amount_mismatch_fails()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Pending->value,
            'expires_at' => now()->addMinutes(15),
            'price' => 100.00
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'status' => PaymentStatus::Pending->value,
            'amount' => 100.00,
            'currency' => 'EUR',
            'provider_session_id' => 'cs_test_mismatch',
        ]);

        $service = new PaymentConfirmationService();
        
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Payment amount does not match reservation amount.');

        $service->confirm($payment, 5000, 'EUR', []); // Paid only 50

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed->value, $payment->status);
        $this->assertIsArray($payment->raw_data);
    }

    public function test_payment_currency_mismatch_fails()
    {
        $reservation = $this->createReservation([
            'status' => ReservationStatus::Pending->value,
            'expires_at' => now()->addMinutes(15),
            'price' => 100.00
        ]);

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'provider' => 'stripe',
            'status' => PaymentStatus::Pending->value,
            'amount' => 100.00,
            'currency' => 'EUR',
            'provider_session_id' => 'cs_test_mismatch_curr',
        ]);

        $service = new PaymentConfirmationService();
        
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Payment currency does not match reservation currency.');

        $service->confirm($payment, 10000, 'USD', []); // Wrong currency

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed->value, $payment->status);
    }
}
