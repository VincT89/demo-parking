<?php

namespace App\Models;

use App\Enums\ShuttleDirection;
use App\Enums\ShuttleTripStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ShuttleTrip extends Model
{
    protected $fillable = [
        'uuid',
        'parking_id',
        'shuttle_vehicle_id',
        'direction',
        'scheduled_at',
        'status',
        'capacity',
        'is_generated',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => ShuttleDirection::class,
            'scheduled_at' => 'datetime',
            'status' => ShuttleTripStatus::class,
            'capacity' => 'integer',
            'is_generated' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $trip): void {
            $trip->uuid ??= (string) Str::uuid();
        });
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(ShuttleVehicle::class, 'shuttle_vehicle_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShuttleTripAssignment::class);
    }

    public function getAssignedPassengersAttribute(): int
    {
        return (int) $this->assignments->sum('passengers');
    }

    public function getRemainingSeatsAttribute(): int
    {
        return max(0, $this->capacity - $this->assigned_passengers);
    }
}
