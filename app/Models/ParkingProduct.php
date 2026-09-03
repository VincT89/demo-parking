<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingProduct extends Model
{
    protected $fillable = [
        'parking_id',
        'code',
        'name',
        'capacity',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(PlatformProductMapping::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
