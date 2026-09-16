<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerVehicle extends Model
{
    public $timestamps = false;

    protected $fillable = ['license_plate'];
}
