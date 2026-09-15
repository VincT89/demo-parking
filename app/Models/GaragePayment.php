<?php

namespace App\Models;

use App\Enums\GaragePaymentMethod;
use App\Enums\GaragePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GaragePayment extends Model
{
    protected $fillable = [
        'parking_id',
        'parking_subscription_id',
        'parking_stay_id',
        'provider',
        'method',
        'status',
        'amount',
        'currency',
        'billing_period_start',
        'billing_period_end',
        'provider_payment_id',
        'provider_session_id',
        'provider_subscription_id',
        'raw_data',
        'recorded_by',
        'paid_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'method' => GaragePaymentMethod::class,
            'status' => GaragePaymentStatus::class,
            'amount' => 'decimal:2',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'raw_data' => 'array',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ParkingSubscription::class, 'parking_subscription_id');
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(ParkingStay::class, 'parking_stay_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
