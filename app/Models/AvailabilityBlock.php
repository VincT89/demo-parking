<?php

namespace App\Models;

use App\Enums\BlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityBlock extends Model
{
    protected $fillable = [
        'parking_id',
        'parking_listing_id',
        'type',
        'starts_at',
        'ends_at',
        'spots',
        'reason',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type'      => BlockType::class,
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'spots'     => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function parkingListing(): BelongsTo
    {
        return $this->belongsTo(ParkingListing::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOverlapping($query, $startsAt, $endsAt)
    {
        return $query->where('starts_at', '<', $endsAt)
                     ->where('ends_at', '>', $startsAt);
    }
}