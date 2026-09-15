<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuttleTripAssignment extends Model
{
    protected $fillable = [
        'shuttle_trip_id',
        'reservation_id',
        'passengers',
    ];

    protected function casts(): array
    {
        return [
            'passengers' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(ShuttleTrip::class, 'shuttle_trip_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
