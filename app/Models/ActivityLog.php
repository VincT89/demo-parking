<?php

namespace App\Models;

use App\Tenancy\ParkingScope;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = [];

    protected $casts = ['changes' => 'array'];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParkingScope);
    }
}
