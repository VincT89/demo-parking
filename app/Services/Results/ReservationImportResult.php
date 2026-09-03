<?php

namespace App\Services\Results;

use App\Models\Reservation;

enum ImportAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Failed = 'failed';
}

class ReservationImportResult
{
    public function __construct(
        public readonly ImportAction $action,
        public readonly ?Reservation $reservation = null,
        public readonly ?string $error = null
    ) {}

    public static function created(Reservation $reservation): self
    {
        return new self(ImportAction::Created, $reservation);
    }

    public static function updated(Reservation $reservation): self
    {
        return new self(ImportAction::Updated, $reservation);
    }

    public static function skipped(?Reservation $reservation = null, string $reason = ''): self
    {
        return new self(ImportAction::Skipped, $reservation, $reason);
    }

    public static function failed(string $error): self
    {
        return new self(ImportAction::Failed, null, $error);
    }

    public function isSuccess(): bool
    {
        return $this->action === ImportAction::Created || $this->action === ImportAction::Updated || $this->action === ImportAction::Skipped;
    }
}
