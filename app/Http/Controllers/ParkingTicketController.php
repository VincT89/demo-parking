<?php

namespace App\Http\Controllers;

use App\Models\ParkingSetting;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParkingTicketController extends Controller
{
    public function show(Request $request, Reservation $reservation)
    {
        $reservation->load(['parking', 'parkingProduct', 'parkingListing.platform']);
        $settings = ParkingSetting::query()->firstOrCreate(['parking_id' => $reservation->parking_id]);

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
            'reservation' => $reservation,
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
