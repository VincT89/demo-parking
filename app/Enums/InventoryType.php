<?php

namespace App\Enums;

enum InventoryType: string
{
  case Shared = 'shared';
  case Isolated = 'isolated';
  case Unknown = 'unknown';

  public function label(): string
  {
    return match ($this) {
      InventoryType::Shared => __('Condiviso'),
      InventoryType::Isolated => __('Dedicato'),
      InventoryType::Unknown => __('Non definito'),
    };
  }
}
