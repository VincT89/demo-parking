<?php

namespace App\Exports;

use App\Models\Reservation;
use App\Enums\ReservationStatus;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class ReservationsExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    public function __construct(
        private ?string $platformId = null,
        private ?string $status     = null,
        private ?string $dateFrom   = null,
        private ?string $dateTo     = null,
        private ?string $dateFilterType = 'starts_at',
    ) {}

    public function query()
    {
        $query = Reservation::query()
            ->with(['parkingListing.platform', 'parkingListing.parking'])
            ->orderByDesc('starts_at');

        if ($this->platformId) {
            $query->whereHas('parkingListing', fn($q) =>
                $q->where('platform_id', $this->platformId)
            );
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $dateColumn = in_array($this->dateFilterType, ['starts_at', 'ends_at'], true)
            ? $this->dateFilterType
            : 'starts_at';

        if ($this->dateFrom) {
            $query->whereDate($dateColumn, '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate($dateColumn, '<=', $this->dateTo);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'Email',
            'Telefono',
            'Targa',
            'Volo',
            'Canale',
            'Parcheggio',
            'Arrivo',
            'Partenza',
            'Posti',
            'Clienti',
            'Prezzo (€)',
            'Stato',
            'Codice esterno',
            'Note',
            'Creata il',
        ];
    }

    public function map($reservation): array
    {
        return [
            $reservation->id,
            $reservation->customer_name,
            $reservation->customer_email ?? '',
            $reservation->customer_phone ?? '',
            $reservation->license_plate ?? '',
            $reservation->flight_reference ?? '',
            $reservation->parkingListing->platform->name,
            $reservation->parkingListing->parking->name,
            $reservation->starts_at->format('d/m/Y H:i'),
            $reservation->ends_at->format('d/m/Y H:i'),
            $reservation->spots,
            $reservation->passengers_count ?? 1,
            $reservation->price ? number_format($reservation->price, 2, ',', '.') : '',
            $reservation->status->label(),
            $reservation->external_id ?? '',
            $reservation->notes ?? '',
            $reservation->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '1e293b'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 28,
            'D' => 16,
            'E' => 12,
            'F' => 14,
            'G' => 18,
            'H' => 22,
            'I' => 16,
            'J' => 16,
            'K' => 8,
            'L' => 8,
            'M' => 12,
            'N' => 14,
            'O' => 18,
            'P' => 30,
            'Q' => 16,
        ];
    }

    public function title(): string
    {
        return 'Prenotazioni';
    }
}