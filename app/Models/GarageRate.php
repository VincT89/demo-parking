<?php

namespace App\Models;

use App\Enums\GarageBillingUnit;
use App\Enums\GarageRateKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GarageRate extends Model
{
    protected $fillable = [
        'parking_id',
        'parking_product_id',
        'name',
        'kind',
        'billing_unit',
        'price',
        'currency',
        'grace_minutes',
        'minimum_units',
        'valid_from',
        'valid_until',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => GarageRateKind::class,
            'billing_unit' => GarageBillingUnit::class,
            'price' => 'decimal:2',
            'grace_minutes' => 'integer',
            'minimum_units' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function parkingProduct(): BelongsTo
    {
        return $this->belongsTo(ParkingProduct::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ParkingSubscription::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(ParkingStay::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, mixed $date): Builder
    {
        return $query
            ->where(fn (Builder $builder) => $builder
                ->whereNull('valid_from')
                ->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $builder) => $builder
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $date));
    }

    public function isEffectiveOn(CarbonInterface $date): bool
    {
        return (! $this->valid_from || $this->valid_from->copy()->startOfDay()->lessThanOrEqualTo($date))
            && (! $this->valid_until || $this->valid_until->copy()->endOfDay()->greaterThanOrEqualTo($date));
    }
}
