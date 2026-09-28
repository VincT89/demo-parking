<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Management extends Model
{
    protected $table = 'managements';

    protected $fillable = ['name'];

    protected $hidden = ['payment_settings'];

    protected function casts(): array
    {
        return ['is_initial' => 'boolean', 'uses_legacy_payments' => 'boolean', 'payment_settings' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $management) => $management->public_key ??= (string) Str::uuid());
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function parkings()
    {
        return $this->hasMany(Parking::class);
    }

    public function platforms()
    {
        return $this->hasMany(Platform::class);
    }
}
