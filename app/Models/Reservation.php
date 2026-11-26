<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\CarWashType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use \App\Models\Concerns\BelongsToParkingContext;

    protected $fillable = [
        'customer_id',
        'parking_id',
        'parking_product_id',
        'parking_listing_id',
        'external_id',
        'platform_created_at',
        'platform_updated_at',
        'platform_cancelled_at',
        'first_seen_at',
        'last_seen_at',
        'customer_name',
        'customer_email',
        'customer_phone',
        'license_plate',
        'flight_reference',
        'starts_at',
        'ends_at',
        'spots',
        'passengers_count',
        'status',
        'price',
        'notes',
        'raw_data',
        'is_valet',
        'car_wash',
        'car_wash_surcharge',
        'expires_at',
        'has_entered',
        'has_exited',
        'whatsapp_review_consent',
    ];

    protected $hidden = ['whatsapp_review_consent'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'platform_created_at' => 'datetime',
            'platform_updated_at' => 'datetime',
            'platform_cancelled_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'status'    => ReservationStatus::class,
            'spots'     => 'integer',
            'passengers_count' => 'integer',
            'price'     => 'decimal:2',
            'raw_data'  => 'array',
            'manually_edited_fields' => 'array',
            'is_valet' => 'boolean',
            'car_wash' => CarWashType::class,
            'car_wash_surcharge' => 'decimal:2',
            'expires_at'=> 'datetime',
            'has_entered' => 'boolean',
            'has_exited' => 'boolean',
            'exited_at' => 'integer',
            'whatsapp_review_consent' => 'encrypted:array',
            'whatsapp_review_attempted_at' => 'integer',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parkingListing(): BelongsTo
    {
        return $this->belongsTo(ParkingListing::class);
    }

    public function parkingProduct(): BelongsTo
    {
        return $this->belongsTo(ParkingProduct::class);
    }

    public function occupiesCapacity(): bool
    {
        return $this->status->isActive()
            && ($this->status !== ReservationStatus::Pending || $this->expires_at?->isFuture());
    }

    public function scopeWithCarWash($query)
    {
        return $query->whereIn('car_wash', array_column(CarWashType::cases(), 'value'));
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Modified->value,
            ])->orWhere(function ($q2) {
                $q2->where('status', ReservationStatus::Pending->value)
                   ->where('expires_at', '>', now());
            });
        });
    }

    public function scopeOverlapping($query, $startsAt, $endsAt)
    {
        return $query->where('starts_at', '<', $endsAt)
                     ->where('ends_at', '>', $startsAt);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
    public function electronicInvoice(): HasOne
    {
        return $this->hasOne(ElectronicInvoice::class);
    }

    public function shuttleAssignments(): HasMany
    {
        return $this->hasMany(ShuttleTripAssignment::class);
    }
}
