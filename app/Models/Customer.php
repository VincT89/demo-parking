<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $attributes = [
        'type' => 'person', 'country' => 'IT', 'vat_country' => 'IT', 'is_active' => true,
    ];

    protected $fillable = [
        'type', 'name', 'email', 'phone', 'fiscal_code', 'vat_number', 'vat_country',
        'recipient_code', 'pec', 'address', 'postal_code', 'city', 'province', 'country',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $customer) {
            $customer->name = trim($customer->name);
            $customer->email = filled($customer->email) ? Str::lower(trim($customer->email)) : null;
            $customer->phone_normalized = self::normalizePhone($customer->phone) ?: null;
            foreach (['fiscal_code', 'vat_number', 'vat_country', 'recipient_code', 'province', 'country'] as $field) {
                $customer->{$field} = filled($customer->{$field}) ? Str::upper(trim($customer->{$field})) : null;
            }
        });
    }

    public static function normalizePhone(?string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone ?? '');
    }

    public static function normalizePlate(string $plate): string
    {
        return Str::upper(preg_replace('/[\s-]+/u', '', trim($plate)));
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ParkingSubscription::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(ParkingStay::class);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }
        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('fiscal_code', 'like', '%'.$search.'%')
                ->orWhere('vat_number', 'like', '%'.$search.'%')
                ->orWhereHas('vehicles', fn (Builder $v) => $v->where('license_plate', 'like', '%'.self::normalizePlate($search).'%'));
            $phone = self::normalizePhone($search);
            if ($phone !== '') {
                $q->orWhere('phone_normalized', 'like', '%'.$phone.'%');
            }
        });
    }
}
