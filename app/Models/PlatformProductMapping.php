<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformProductMapping extends Model
{
    protected $fillable = [
        'platform_id',
        'parking_product_id',
        'external_ref',
        'external_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function parkingProduct(): BelongsTo
    {
        return $this->belongsTo(ParkingProduct::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
