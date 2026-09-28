<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerVehicle extends Model
{
    use \App\Models\Concerns\BelongsToParkingContext;

    public $timestamps = false;

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected $fillable = ['license_plate'];
}
