<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => \App\Enums\UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->currentRole() === \App\Enums\UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->currentRole() === \App\Enums\UserRole::Staff;
    }

    public function managements()
    {
        return $this->belongsToMany(Management::class)->withPivot('role')->withTimestamps();
    }

    public function currentRole(): ?\App\Enums\UserRole
    {
        $context = app(\App\Tenancy\ParkingContext::class);
        if (! $context->restricted || ! $context->management) {
            return null;
        }
        $role = $this->managements()->whereKey($context->management->id)->first()?->pivot->role;
        return $role ? \App\Enums\UserRole::tryFrom($role) : null;
    }
}
