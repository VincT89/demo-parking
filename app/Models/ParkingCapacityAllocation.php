<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ParkingCapacityAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'parking_id',
        'parking_product_id',
        'allocation_type',
        'spots',
        'starts_at',
        'ends_at',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function parking()
    {
        return $this->belongsTo(Parking::class);
    }

    public function parkingProduct()
    {
        return $this->belongsTo(ParkingProduct::class);
    }

    /**
     * Scope: solo attivi
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: overlappano con il periodo [start, end)
     * Stessa logica temporale delle reservation/blocks (chiuso-aperto)
     */
    public function scopeOverlapping(Builder $query, Carbon $startsAt, Carbon $endsAt)
    {
        return $query->where(function ($q) use ($startsAt, $endsAt) {
            $q->where('starts_at', '<', $endsAt)
              ->where('ends_at', '>', $startsAt);
        });
    }
}
