<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id',
        'provider',
        'status',
        'amount',
        'currency',
        'provider_payment_id',
        'provider_order_id',
        'provider_session_id',
        'raw_data',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_data' => 'array',
        'paid_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
