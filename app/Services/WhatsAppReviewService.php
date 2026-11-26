<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Parking;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WhatsAppReviewService
{
    public const MESSAGE = <<<'TEXT'
        Grazie per aver scelto {{1}}. Se ti va, raccontaci com’è andata la tua sosta lasciando una recensione su Google.

        {{2}}

        Per rinunciare ai prossimi inviti, rispondi STOP.
        TEXT;

    public const DELAY_SECONDS = 24 * 60 * 60;

    public static function internationalNumber(?string $phone): ?string
    {
        $number = preg_replace('/[\s().-]+/u', '', $phone ?? '');
        if ($number === null) {
            return null;
        }
        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        }

        return preg_match('/\A\+[1-9][0-9]{6,14}\z/', $number) ? $number : null;
    }

    public static function consent(string $number, string $source): array
    {
        return ['number' => $number, 'recorded_at' => now()->timestamp, 'source' => $source];
    }

    public static function consentedNumber(Reservation $reservation): ?string
    {
        $number = self::internationalNumber($reservation->customer_phone);

        return $number === ($reservation->whatsapp_review_consent['number'] ?? null) ? $number : null;
    }

    public function sendDue(Parking $parking): void
    {
        $this->dueReservations($parking)->select('id')->eachById(function (Reservation $reservation) use ($parking): void {
            $invitation = $this->claim($parking->id, $reservation->id);
            if ($invitation === null) {
                return;
            }

            $result = $this->send($invitation['settings'], $invitation['number'], $invitation['parking_name']);
            Reservation::where('parking_id', $parking->id)
                ->whereKey($reservation->id)
                ->where('whatsapp_review_status', 'sending')
                ->update($result);
        }, 100);
    }

    private function dueReservations(Parking $parking): Builder
    {
        return Reservation::where('parking_id', $parking->id)
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Modified->value])
            ->where('has_exited', true)
            ->whereBetween('exited_at', [$parking->whatsapp_reviews_enabled_at, now()->timestamp - self::DELAY_SECONDS])
            ->whereNotNull('whatsapp_review_consent')
            ->whereNull('whatsapp_review_status');
    }

    private function claim(int $parkingId, int $reservationId): ?array
    {
        return DB::transaction(function () use ($parkingId, $reservationId): ?array {
            $parking = Parking::lockForUpdate()->find($parkingId);
            if (! $parking?->whatsappReviewsEnabled()) {
                return null;
            }

            $reservation = $this->dueReservations($parking)->lockForUpdate()->find($reservationId);
            $number = $reservation ? self::consentedNumber($reservation) : null;
            if ($number === null) {
                return null;
            }

            /** Registra il tentativo prima della chiamata per evitare duplicati se Twilio risponde in ritardo. */
            $claimed = Reservation::where('parking_id', $parkingId)->whereKey($reservationId)
                ->whereNull('whatsapp_review_status')->update([
                    'whatsapp_review_status' => 'sending',
                    'whatsapp_review_attempted_at' => now()->timestamp,
                ]);

            return $claimed === 1 ? [
                'settings' => $parking->whatsapp_review_settings,
                'number' => $number,
                'parking_name' => $parking->name,
            ] : null;
        }, 3);
    }

    private function send(array $settings, string $number, string $parkingName): array
    {
        if (config('demo.enabled')) {
            return ['whatsapp_review_status' => 'simulated'];
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($settings['api_key_sid'], $settings['api_key_secret'])
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(12)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.$settings['account_sid'].'/Messages.json', [
                    'From' => 'whatsapp:'.$settings['sender'],
                    'To' => 'whatsapp:'.$number,
                    'MessagingServiceSid' => $settings['messaging_service_sid'],
                    'ContentSid' => $settings['content_sid'],
                    'ContentVariables' => json_encode([
                        '1' => str($parkingName)->squish()->value(),
                        '2' => $settings['review_url'],
                    ], JSON_THROW_ON_ERROR),
                ]);
        } catch (ConnectionException) {
            return ['whatsapp_review_status' => 'uncertain'];
        }

        return $this->result($response);
    }

    private function result(Response $response): array
    {
        if ($response->clientError()) {
            return ['whatsapp_review_status' => 'failed'];
        }

        $sid = $response->json('sid');
        if (! $response->successful() || ! is_string($sid) || ! preg_match('/\A(?:SM|MM)[0-9a-fA-F]{32}\z/', $sid)) {
            return ['whatsapp_review_status' => 'uncertain'];
        }

        return [
            'whatsapp_review_status' => in_array($response->json('status'), ['failed', 'undelivered', 'canceled'], true) ? 'failed' : 'accepted',
            'whatsapp_review_message_sid' => $sid,
        ];
    }
}
