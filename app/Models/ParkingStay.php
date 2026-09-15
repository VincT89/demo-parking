<?php

namespace App\Models;

use App\Enums\GarageBillingUnit;
use App\Enums\ParkingStayStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ParkingStay extends Model
{
    protected $fillable = [
        'uuid',
        'parking_id',
        'parking_product_id',
        'garage_rate_id',
        'parking_subscription_id',
        'reference',
        'customer_name',
        'customer_email',
        'customer_phone',
        'license_plate',
        'starts_at',
        'expected_ends_at',
        'ended_at',
        'status',
        'billing_unit',
        'unit_price',
        'estimated_total',
        'total_amount',
        'currency',
        'payment_status',
        'notes',
        'created_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expected_ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => ParkingStayStatus::class,
            'billing_unit' => GarageBillingUnit::class,
            'unit_price' => 'decimal:2',
            'estimated_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $stay): void {
            $stay->uuid ??= (string) Str::uuid();
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ParkingSubscription::class, 'parking_subscription_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GaragePayment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(GaragePayment::class)->latestOfMany();
    }

    public function scopeOccupyingBetween(
        Builder $query,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): Builder {
        return $query
            ->where('starts_at', '<', $endsAt)
            ->where(function (Builder $builder) use ($startsAt): void {
                $builder->where(function (Builder $active) use ($startsAt): void {
                    $active
                        ->where('status', ParkingStayStatus::Active->value)
                        ->where('expected_ends_at', '>', $startsAt);
                })->orWhere(function (Builder $completed) use ($startsAt): void {
                    $completed
                        ->where('status', ParkingStayStatus::Completed->value)
                        ->where('ended_at', '>', $startsAt);
                });
            });
    }
}
