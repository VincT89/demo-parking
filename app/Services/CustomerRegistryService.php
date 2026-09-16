<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ParkingStay;
use App\Models\ParkingSubscription;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerRegistryService
{
    public function duplicates(array $data, ?Customer $except = null)
    {
        return Customer::query()->when($except?->exists, fn ($q) => $q->whereKeyNot($except->id))
            ->where(function ($q) use ($data) {
                $q->whereRaw('1 = 0');
                foreach (['email', 'fiscal_code', 'vat_number'] as $field) {
                    if (filled($data[$field] ?? null)) {
                        $value = $field === 'email' ? Str::lower(trim($data[$field])) : Str::upper(trim($data[$field]));
                        $q->orWhere(function ($match) use ($field, $value, $data) {
                            $match->where($field, $value);
                            if ($field === 'vat_number') {
                                $match->where('vat_country', Str::upper($data['vat_country'] ?? 'IT'));
                            }
                        });
                    }
                }
                $phone = Customer::normalizePhone($data['phone'] ?? null);
                if ($phone !== '') {
                    $q->orWhere('phone_normalized', $phone);
                }
            })->orderBy('name')->limit(10)->get();
    }

    public function save(array $data, ?Customer $customer = null): Customer
    {
        if ($this->duplicates($data, $customer)->isNotEmpty() && ! ($data['confirm_duplicate'] ?? false)) {
            throw ValidationException::withMessages([
                'confirm_duplicate' => __('Esistono clienti con dati coincidenti. Verifica le schede e conferma se sono persone diverse.'),
            ]);
        }

        return DB::transaction(function () use ($data, $customer) {
            $plates = collect(preg_split('/\R/u', $data['license_plates'] ?? ''))
                ->map(fn ($plate) => Customer::normalizePlate($plate))->filter()->unique()->values();
            if ($plates->count() > 30 || $plates->contains(fn ($plate) => mb_strlen($plate) > 32)) {
                throw ValidationException::withMessages(['license_plates' => __('Inserisci al massimo 30 targhe, fino a 32 caratteri ciascuna.')]);
            }
            unset($data['license_plates'], $data['confirm_duplicate']);
            $customer ??= new Customer;
            $customer->fill($data)->save();
            $customer->vehicles()->whereNotIn('license_plate', $plates)->delete();
            foreach ($plates as $plate) {
                $customer->vehicles()->firstOrCreate(['license_plate' => $plate]);
            }
            return $customer;
        });
    }

    public function operationCustomer(array $data, ?int $currentId = null): ?int
    {
        if (! array_key_exists('customer_id', $data)) {
            return $currentId;
        }
        if ($currentId && (int) ($data['customer_id'] ?? 0) !== $currentId) {
            throw ValidationException::withMessages(['customer_id' => __('L’operazione è già collegata a un altro cliente.')]);
        }
        if (empty($data['customer_id'])) {
            return null;
        }
        $customer = Customer::query()->findOrFail($data['customer_id']);
        if (! $customer->is_active && $customer->id !== $currentId) {
            throw ValidationException::withMessages(['customer_id' => __('Il cliente è archiviato. Riattivalo prima di collegare nuove operazioni.')]);
        }
        return $customer->id;
    }

    public function link(Customer $customer, string $type, int $id): void
    {
        DB::transaction(function () use ($customer, $type, $id) {
            $model = match ($type) {
                'reservation' => Reservation::class,
                'subscription' => ParkingSubscription::class,
                'stay' => ParkingStay::class,
            };
            // Lock a parent subscription before its stays, matching the check-in lock order.
            if ($type === 'stay') {
                $subscriptionId = ParkingStay::query()->findOrFail($id)->parking_subscription_id;
                if ($subscriptionId) {
                    $subscription = ParkingSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
                    if ((int) $subscription->customer_id !== $customer->id) {
                        throw ValidationException::withMessages(['record' => __('Collega prima l’abbonamento allo stesso cliente.')]);
                    }
                }
            }
            $record = $model::query()->lockForUpdate()->findOrFail($id);
            // Operational rows precede the customer lock, as in subscription updates.
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['customer' => __('Il cliente è archiviato. Riattivalo prima di collegare nuove operazioni.')]);
            }
            if ($record->customer_id && (int) $record->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['record' => __('L’operazione è già collegata a un altro cliente.')]);
            }
            if ($type === 'subscription') {
                $stays = $record->stays()->lockForUpdate()->get();
                if ($stays->contains(fn ($stay) => $stay->customer_id && (int) $stay->customer_id !== $customer->id)) {
                    throw ValidationException::withMessages(['record' => __('L’operazione è già collegata a un altro cliente.')]);
                }
                $record->stays()->whereNull('customer_id')->update(['customer_id' => $customer->id]);
            }
            $record->update(['customer_id' => $customer->id]);
        });
    }
}
