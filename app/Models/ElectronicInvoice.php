<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectronicInvoice extends Model
{
    protected $fillable = [
        'reservation_id',
        'parking_id',
        'number',
        'sequence',
        'year',
        'provider_mode',
        'status',
        'customer_type',
        'customer_name',
        'customer_vat_country',
        'customer_vat_number',
        'customer_fiscal_code',
        'customer_recipient_code',
        'customer_pec',
        'customer_address',
        'customer_postal_code',
        'customer_city',
        'customer_province',
        'customer_country',
        'taxable_amount',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'xml_payload',
        'remote_id',
        'remote_filename',
        'sdi_id',
        'last_error',
        'status_history',
        'submitted_at',
        'delivered_at',
        'last_status_check_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'year' => 'integer',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status_history' => 'array',
            'submitted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_status_check_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['delivered', 'accepted', 'rejected', 'delivery_failed', 'expired_terms'], true);
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor($this->status);
    }

    public static function statusLabelFor(string $status): string
    {
        return match ($status) {
            'draft' => __('Bozza'),
            'processing' => __('Presa in carico'),
            'submitted' => __('Inviata a SdI'),
            'delivered' => __('Consegnata'),
            'accepted' => __('Accettata'),
            'rejected' => __('Scartata'),
            'delivery_failed' => __('Consegna non riuscita'),
            'expired_terms' => __('Decorrenza termini'),
            'error' => __('Errore'),
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'delivered', 'accepted' => 'green',
            'processing', 'submitted', 'expired_terms' => 'blue',
            'rejected', 'delivery_failed', 'error' => 'red',
            default => 'gray',
        };
    }
}
