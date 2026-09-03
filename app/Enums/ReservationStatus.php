<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Modified = 'modified';
    case NoShow = 'no_show';

    public function label(): string
    {
      return match($this) {
        ReservationStatus::Pending => __('In attesa'),
        ReservationStatus::Confirmed => __('Confermata'),
        ReservationStatus::Cancelled => __('Annullata'),
        ReservationStatus::Modified => __('Modificata'),
        ReservationStatus::NoShow => __('Non presentato'),
      };
    }

    public function isActive(): bool
    {
      return in_array($this, [
        ReservationStatus::Pending,
        ReservationStatus::Confirmed,
        ReservationStatus::Modified,
      ]);
    }

}
