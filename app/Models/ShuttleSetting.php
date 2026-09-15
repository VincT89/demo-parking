<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuttleSetting extends Model
{
    protected $fillable = [
        'parking_id',
        'is_enabled',
        'default_capacity',
        'grouping_window_minutes',
        'turnaround_minutes',
        'outbound_offset_minutes',
        'return_offset_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'default_capacity' => 'integer',
            'grouping_window_minutes' => 'integer',
            'turnaround_minutes' => 'integer',
            'outbound_offset_minutes' => 'integer',
            'return_offset_minutes' => 'integer',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }
}
