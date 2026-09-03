<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'platform_id',
        'parking_listing_id',
        'source',
        'status',
        'is_dry_run',
        'reservations_created',
        'reservations_updated',
        'reservations_failed',
        'reservations_skipped',
        'notes',
        'raw_payload',
        'window_from',
        'window_to',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload'           => 'array',
            'is_dry_run'            => 'boolean',
            'reservations_created'  => 'integer',
            'reservations_updated'  => 'integer',
            'reservations_failed'   => 'integer',
            'reservations_skipped'  => 'integer',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function parkingListing(): BelongsTo
    {
        return $this->belongsTo(ParkingListing::class);
    }
}