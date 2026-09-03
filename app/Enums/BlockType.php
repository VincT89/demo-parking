<?php

namespace App\Enums;

enum BlockType: string
{
  case Maintenance = 'maintenance';
  case Closure = 'closure';
  case Internal = 'internal';
  case Other = 'other';

  public function label(): string
  {
    return match ($this) {
      BlockType::Maintenance => __('Manutenzione'),
      BlockType::Closure => __('Chiusura'),
      BlockType::Internal => __('Interno'),
      BlockType::Other => __('Altro'),
    };
  }
}
