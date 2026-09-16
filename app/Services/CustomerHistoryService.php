<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerHistoryService
{
    public function query(Customer $customer)
    {
        $reservation = DB::table('reservations as r')->join('parkings as p', 'p.id', '=', 'r.parking_id')
            ->where('r.customer_id', $customer->id)
            ->selectRaw("'reservation' as kind, r.id, r.starts_at as event_at, r.external_id as reference, p.name as parking_name, r.license_plate, r.status, r.price as amount, 'EUR' as currency, r.customer_name as recorded_name");
        $subscription = DB::table('parking_subscriptions as r')->join('parkings as p', 'p.id', '=', 'r.parking_id')
            ->where('r.customer_id', $customer->id)
            ->selectRaw("'subscription' as kind, r.id, r.starts_on as event_at, r.reference, p.name as parking_name, r.license_plate, r.status, r.price as amount, r.currency, r.customer_name as recorded_name");
        $stay = DB::table('parking_stays as r')->join('parkings as p', 'p.id', '=', 'r.parking_id')
            ->where('r.customer_id', $customer->id)
            ->selectRaw("'stay' as kind, r.id, r.starts_at as event_at, r.reference, p.name as parking_name, r.license_plate, r.status, r.total_amount as amount, r.currency, r.customer_name as recorded_name");
        $payments = DB::table('payments as e')->join('reservations as r', 'r.id', '=', 'e.reservation_id')
            ->join('parkings as p', 'p.id', '=', 'r.parking_id')->where('r.customer_id', $customer->id)
            ->selectRaw("'payment' as kind, e.id, COALESCE(e.paid_at, e.created_at) as event_at, r.external_id as reference, p.name as parking_name, r.license_plate, e.status, e.amount, e.currency, r.customer_name as recorded_name");
        $garagePayments = DB::table('garage_payments as e')
            ->leftJoin('parking_subscriptions as s', 's.id', '=', 'e.parking_subscription_id')
            ->leftJoin('parking_stays as t', 't.id', '=', 'e.parking_stay_id')
            ->join('parkings as p', 'p.id', '=', 'e.parking_id')
            ->where(fn ($q) => $q->where('s.customer_id', $customer->id)->orWhere('t.customer_id', $customer->id))
            ->selectRaw("'garage_payment' as kind, e.id, COALESCE(e.paid_at, e.created_at) as event_at, COALESCE(t.reference, s.reference) as reference, p.name as parking_name, COALESCE(t.license_plate, s.license_plate) as license_plate, e.status, e.amount, e.currency, COALESCE(t.customer_name, s.customer_name) as recorded_name");
        $invoices = DB::table('electronic_invoices as e')->join('reservations as r', 'r.id', '=', 'e.reservation_id')
            ->join('parkings as p', 'p.id', '=', 'e.parking_id')->where('r.customer_id', $customer->id)
            ->selectRaw("'invoice' as kind, e.id, e.created_at as event_at, e.number as reference, p.name as parking_name, r.license_plate, e.status, e.total_amount as amount, 'EUR' as currency, e.customer_name as recorded_name");

        return DB::query()->fromSub($reservation->unionAll($subscription)->unionAll($stay)
            ->unionAll($payments)->unionAll($garagePayments)->unionAll($invoices), 'customer_history');
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            'reservation' => __('Prenotazione'),
            'subscription' => __('Abbonamento'),
            'stay' => __('Sosta garage'),
            'payment', 'garage_payment' => __('Pagamento'),
            'invoice' => __('Fattura'),
        };
    }

    public static function status(string $kind, string $status): string
    {
        return match ($kind) {
            'reservation' => \App\Enums\ReservationStatus::from($status)->label(),
            'subscription' => \App\Enums\SubscriptionStatus::from($status)->label(),
            'stay' => \App\Enums\ParkingStayStatus::from($status)->label(),
            'invoice' => \App\Models\ElectronicInvoice::statusLabelFor($status),
            default => match ($status) {
                'paid' => __('Pagato'),
                'pending' => __('In attesa'),
                'failed' => __('Non riuscito'),
                'refunded' => __('Rimborsato'),
                'reversed' => __('Stornato'),
                'cancelled' => __('Annullato'),
                'expired' => __('Scaduto'),
                default => __('Sconosciuto'),
            },
        };
    }
}
