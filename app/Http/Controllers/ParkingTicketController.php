<?php

namespace App\Http\Controllers;

use App\Models\ParkingSetting;
use App\Models\ParkingStay;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParkingTicketController extends Controller
{
    public function show(Request $request, Reservation $reservation)
    {
        $reservation->load(['parking', 'parkingProduct', 'parkingListing.platform']);

        return $this->render($request, $reservation->parking_id, [
            'reference' => $reservation->external_id,
            'parking' => $reservation->parking,
            'customer_name' => $reservation->customer_name,
            'license_plate' => $reservation->license_plate,
            'starts_at' => $reservation->starts_at,
            'pickup_at' => $reservation->ends_at,
            'pickup_label' => __('Ritiro previsto'),
            'type' => __($reservation->parkingProduct?->name ?? '—'),
            'passengers' => $reservation->passengers_count ?? 1,
            'back_url' => route('reservations.show', $reservation),
            'preview_url' => route('reservations.ticket', $reservation),
        ]);
    }

    public function showStay(Request $request, ParkingStay $stay): View
    {
        $stay->load(['parking', 'parkingProduct', 'rate', 'subscription']);

        return $this->render($request, $stay->parking_id, [
            'reference' => $stay->reference,
            'parking' => $stay->parking,
            'customer_name' => $stay->customer_name,
            'license_plate' => $stay->license_plate,
            'starts_at' => $stay->starts_at,
            'pickup_at' => $stay->ended_at ?? $stay->expected_ends_at,
            'pickup_label' => $stay->ended_at ? __('Uscita effettiva') : __('Uscita prevista'),
            'type' => $stay->subscription
                ? __('Abbonamento')
                : ($stay->rate?->name ?? __('Giornaliero')),
            'passengers' => null,
            'back_url' => route('garage.stays.show', $stay),
            'preview_url' => route('garage.stays.ticket', $stay),
        ]);
    }

    private function render(Request $request, int $parkingId, array $ticket): View
    {
        $settings = ParkingSetting::query()->firstOrCreate(['parking_id' => $parkingId]);

        $validated = validator($request->query(), [
            'format' => ['nullable', Rule::in(['a4', 'a6', 'label', 'custom'])],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
            'width' => ['nullable', 'integer', 'min:30', 'max:216'],
            'height' => ['nullable', 'integer', 'min:30', 'max:356'],
            'autoprint' => ['nullable', 'boolean'],
        ])->validate();

        $format = $validated['format'] ?? $settings->ticket_format;
        $orientation = $validated['orientation'] ?? $settings->ticket_orientation;
        $customWidth = (int) ($validated['width'] ?? $settings->ticket_width_mm);
        $customHeight = (int) ($validated['height'] ?? $settings->ticket_height_mm);
        [$width, $height] = match ($format) {
            'a4' => [210, 297],
            'a6' => [105, 148],
            default => [$customWidth, $customHeight],
        };

        if ($orientation === 'landscape') {
            [$width, $height] = [$height, $width];
        }

        return view('reservations.ticket', [
            'ticket' => $ticket,
            'settings' => $settings,
            'format' => $format,
            'orientation' => $orientation,
            'paperWidth' => $width,
            'paperHeight' => $height,
            'customWidth' => $customWidth,
            'customHeight' => $customHeight,
            'autoprint' => (bool) ($validated['autoprint'] ?? false),
        ]);
    }
}
