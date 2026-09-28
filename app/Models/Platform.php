<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model 
{
  use \App\Models\Concerns\BelongsToParkingContext;

  protected $hidden = ['connection_settings'];

  public function management()
  {
      return $this->belongsTo(Management::class);
  }

  protected $fillable = [
    'name',
    'slug',
    'website',
    'contact_email',
    'is_active',
  ];

  protected function casts(): array
  {
    return [
      'uses_legacy_credentials' => 'boolean',
      'connection_settings' => 'encrypted:array',
      'is_active' => 'boolean',
    ];
  }

  public function listings(): HasMany
  {
    return $this->hasMany(ParkingListing::class);
  }

  public function syncLogs(): HasMany
  {
    return $this->hasMany(SyncLog::class);
  }

  public function productMappings(): HasMany
  {
    return $this->hasMany(PlatformProductMapping::class);
  }

  public function scopeActive($query)
  {
      return $query->where('is_active', true);
  }
}
