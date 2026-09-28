<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingListing extends Model
{
    use \App\Models\Concerns\BelongsToParkingContext;

    protected static function booted(): void
    {
        static::saving(function (self $listing) {
            $parking = Parking::findOrFail($listing->parking_id);
            $platform = Platform::findOrFail($listing->platform_id);
            if ($parking->management_id !== $platform->management_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['platform_id' => 'Il canale appartiene a un’altra gestione.']);
            }
        });
    }

    protected $fillable = [
        'parking_id',
        'platform_id',
        'is_active',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
