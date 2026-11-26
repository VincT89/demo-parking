<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\Reservation;
use App\Services\WhatsAppReviewService;
use App\Tenancy\ParkingContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WhatsAppReviewController extends Controller
{
    public function edit(Parking $parking)
    {
        $settings = $parking->whatsapp_review_settings ?? [];

        return view('parkings.whatsapp-reviews', [
            'parking' => $parking,
            'settings' => Arr::except($settings, ['api_key_secret']),
            'hasSecret' => filled($settings['api_key_secret'] ?? null),
            'message' => WhatsAppReviewService::MESSAGE,
        ]);
    }

    public function update(Request $request, Parking $parking)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'account_sid' => ['nullable', 'regex:/\AAC[0-9a-fA-F]{32}\z/'],
            'api_key_sid' => ['nullable', 'regex:/\ASK[0-9a-fA-F]{32}\z/'],
            'credentials' => ['sometimes', 'array:api_key_secret'],
            'credentials.api_key_secret' => ['nullable', 'string', 'max:255', 'regex:/\A[!-~]+\z/'],
            'sender' => ['nullable', 'regex:/\A\+[1-9][0-9]{6,14}\z/'],
            'messaging_service_sid' => ['nullable', 'regex:/\AMG[0-9a-fA-F]{32}\z/'],
            'content_sid' => ['nullable', 'regex:/\AHX[0-9a-fA-F]{32}\z/'],
            'review_url' => ['bail', 'nullable', 'string', 'url:https', 'max:512', function (string $attribute, string $value, Closure $fail): void {
                if (! $this->isGoogleReviewLink($value)) {
                    $fail(__('Incolla il link per richiedere recensioni dal Profilo dell’attività su Google.'));
                }
            }],
        ], [
            'required' => __('Compila il campo :attribute.'),
            'boolean' => __('Il valore del campo :attribute non è valido.'),
            'string' => __('Il valore del campo :attribute non è valido.'),
            'max' => __('Il campo :attribute supera il limite di :max caratteri.'),
            'account_sid.regex' => __('Inserisci l’Account SID di Twilio (AC seguito da 32 caratteri esadecimali).'),
            'api_key_sid.regex' => __('Inserisci l’API Key SID di Twilio (SK seguito da 32 caratteri esadecimali).'),
            'credentials.api_key_secret.regex' => __('Inserisci il segreto della chiave Twilio senza spazi.'),
            'sender.regex' => __('Inserisci il numero mittente con il prefisso internazionale, ad esempio +39.'),
            'messaging_service_sid.regex' => __('Inserisci il Messaging Service SID di Twilio (MG seguito da 32 caratteri esadecimali).'),
            'content_sid.regex' => __('Inserisci il Content SID del modello approvato (HX seguito da 32 caratteri esadecimali).'),
            'review_url.url' => __('Inserisci un link Google che inizi con https://.'),
        ]);

        DB::transaction(function () use ($parking, $data): void {
            $current = Parking::lockForUpdate()->findOrFail($parking->id);
            $previous = $current->whatsapp_review_settings ?? [];
            $settings = array_replace($previous, Arr::except($data, ['enabled', 'credentials']));
            $sameKey = ($settings['account_sid'] ?? null) === ($previous['account_sid'] ?? null)
                && ($settings['api_key_sid'] ?? null) === ($previous['api_key_sid'] ?? null);
            $settings['api_key_secret'] = $data['credentials']['api_key_secret']
                ?? ($sameKey ? ($previous['api_key_secret'] ?? null) : null);

            if ($data['enabled']) {
                $required = config('demo.enabled')
                    ? ['review_url']
                    : ['account_sid', 'api_key_sid', 'api_key_secret', 'sender', 'messaging_service_sid', 'content_sid', 'review_url'];
                foreach ($required as $field) {
                    if (blank($settings[$field] ?? null)) {
                        throw ValidationException::withMessages(['enabled' => __('Completa i dati di Twilio e il link Google prima di attivare gli invii.')]);
                    }
                }
                if (! $current->is_active) {
                    throw ValidationException::withMessages(['enabled' => __('Attiva il parcheggio prima di attivare gli invii.')]);
                }
            }

            if ($settings !== $previous) {
                $current->whatsapp_review_settings = $settings;
            }
            $current->whatsapp_reviews_enabled_at = $data['enabled']
                ? ($current->whatsapp_reviews_enabled_at ?? now()->timestamp)
                : null;
            $current->save();
        }, 3);

        return redirect()->route('parkings.whatsapp-reviews.edit', $parking)
            ->with('success', __('Impostazioni WhatsApp salvate.'));
    }

    public function updateConsent(Request $request, Reservation $reservation, ParkingContext $context)
    {
        $grant = (bool) $request->validate(['consent' => ['required', 'boolean']], ['consent.required' => __('Seleziona un valore valido per il consenso WhatsApp.'), 'consent.boolean' => __('Seleziona un valore valido per il consenso WhatsApp.')])['consent'];

        DB::transaction(function () use ($reservation, $grant, $context): void {
            $current = Reservation::lockForUpdate()->findOrFail($reservation->id);
            if (! $grant) {
                $current->whatsapp_review_consent = null;
                $current->save();

                return;
            }

            abort_unless($context->parking?->whatsappReviewsEnabled(), 422, __('Gli inviti WhatsApp sono disattivati per questo parcheggio.'));
            $number = WhatsAppReviewService::internationalNumber($current->customer_phone);
            if ($number === null) {
                throw ValidationException::withMessages(['consent' => __('Inserisci il numero del cliente con il prefisso internazionale.')]);
            }

            $current->whatsapp_review_consent = WhatsAppReviewService::consent($number, 'operator');
            $current->save();
        }, 3);

        return redirect()->route('reservations.show', $reservation)
            ->with('success', $grant ? __('Consenso WhatsApp registrato.') : __('Consenso WhatsApp revocato.'));
    }

    private function isGoogleReviewLink(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }

        $path = $parts['path'] ?? '';
        return match (strtolower($parts['host'] ?? '')) {
            'g.page' => preg_match('~\A/(?:r/)?[A-Za-z0-9_-]+/review/?\z~', $path) === 1,
            'search.google.com' => $path === '/local/writereview'
                && preg_match('/\Aplaceid=[A-Za-z0-9_-]+\z/', $parts['query'] ?? '') === 1,
            default => false,
        };
    }
}
