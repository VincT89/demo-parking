<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
      return match($this) {
        self::Admin => __('Admin'),
        self::Staff => __('Staff'),
      };
    }

    public function isAdmin(): bool
    {
        return $this === UserRole::Admin;
    }
}
