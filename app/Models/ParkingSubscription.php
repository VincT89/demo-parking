<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ParkingSubscription extends Model
{
    protected $fillable = [
        'uuid',
        'parking_id',
        'parking_product_id',
        'garage_rate_id',
        'reference',
        'customer_name',
        'customer_email',
        'customer_phone',
        'license_plate',
        'starts_on',
        'ends_on',
        'paid_through',
        'next_billing_on',
        'status',
        'reserved_spots',
        'price',
        'currency',
        'payment_mode',
        'stripe_customer_id',
        'stripe_subscription_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'paid_through' => 'date',
            'next_billing_on' => 'date',
            'status' => SubscriptionStatus::class,
            'reserved_spots' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            $subscription->uuid ??= (string) Str::uuid();
        });
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function parkingProduct(): BelongsTo
    {
        return $this->belongsTo(ParkingProduct::class);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(GarageRate::class, 'garage_rate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(ParkingStay::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GaragePayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(GaragePayment::class)->latestOfMany();
    }

    public function scopeReservingBetween(
        Builder $query,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
    ): Builder {
        $query
            ->where('status', SubscriptionStatus::Active->value)
            ->where(fn (Builder $builder) => $builder
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $startsAt->toDateString()));

        if ($endsAt) {
            $query->whereDate('starts_on', '<=', $endsAt->toDateString());
        }

        return $query;
    }

    public function isPaidThroughToday(): bool
    {
        return $this->paid_through !== null
            && $this->paid_through->copy()->endOfDay()->greaterThanOrEqualTo(now());
    }
}
