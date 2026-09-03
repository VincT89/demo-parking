<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingSetting extends Model
{
    protected $fillable = [
        'parking_id',
        'invoice_mode',
        'aruba_username',
        'aruba_password',
        'issuer_business_name',
        'issuer_vat_country',
        'issuer_vat_number',
        'issuer_fiscal_code',
        'issuer_tax_regime',
        'issuer_address',
        'issuer_postal_code',
        'issuer_city',
        'issuer_province',
        'issuer_country',
        'invoice_prefix',
        'next_invoice_number',
        'default_vat_rate',
        'prices_include_vat',
        'ticket_format',
        'ticket_orientation',
        'ticket_width_mm',
        'ticket_height_mm',
        'ticket_auto_open_on_exit',
        'ticket_show_logo',
        'ticket_title',
        'ticket_footer',
    ];

    protected function casts(): array
    {
        return [
            'aruba_username' => 'encrypted',
            'aruba_password' => 'encrypted',
            'next_invoice_number' => 'integer',
            'default_vat_rate' => 'decimal:2',
            'prices_include_vat' => 'boolean',
            'ticket_width_mm' => 'integer',
            'ticket_height_mm' => 'integer',
            'ticket_auto_open_on_exit' => 'boolean',
            'ticket_show_logo' => 'boolean',
        ];
    }

    public function parking(): BelongsTo
    {
        return $this->belongsTo(Parking::class);
    }

    public function usesAruba(): bool
    {
        return in_array($this->invoice_mode, ['aruba_demo', 'aruba_production'], true);
    }

    public function isSimulator(): bool
    {
        return $this->invoice_mode === 'simulator';
    }

    public function hasArubaCredentials(): bool
    {
        return filled($this->aruba_username) && filled($this->aruba_password);
    }
}
