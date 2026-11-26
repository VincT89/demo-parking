<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="pm-page-title">{{ __('WhatsApp e recensioni') }}</div>
            <div class="pm-page-subtitle">{{ $parking->name }}</div>
        </div>
        <a href="{{ route('parkings.index') }}" class="pm-btn pm-btn-secondary">{{ __('Torna ai parcheggi') }}</a>
    </x-slot>

    <div class="pm-form">
        <x-flash-message />
        @if(config('demo.enabled'))
            <p class="pm-form-note" role="status">{{ __('In modalità demo gli inviti sono simulati e non vengono inviati messaggi reali. Le credenziali Twilio non sono necessarie.') }}</p>
        @endif
        @if($errors->any())
            <div class="pm-alert pm-alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form class="pm-card pm-form" method="POST" action="{{ route('parkings.whatsapp-reviews.update', $parking) }}" autocomplete="off">
            @csrf
            @parkingContext
            @method('PUT')

            <div class="pm-form-group">
                <input type="hidden" name="enabled" value="0">
                <div class="pm-checkbox-group">
                    <input id="whatsapp-reviews-enabled" class="pm-checkbox" type="checkbox" name="enabled" value="1"
                           aria-describedby="review-timing" @checked(in_array(old('enabled', $parking->whatsappReviewsEnabled()), [true, 1, '1'], true))>
                    <label for="whatsapp-reviews-enabled" class="pm-checkbox-label">{{ __('Attiva gli invii automatici') }}</label>
                </div>
                <p id="review-timing" class="pm-form-note">{{ __('Un invito per prenotazione, a partire da 24 ore dopo l’uscita registrata e con il consenso del cliente. Vale per le uscite registrate dopo l’attivazione.') }}</p>
            </div>

            <div class="pm-form-group">
                <label for="review_url" class="pm-label">{{ __('Link alla recensione Google') }}</label>
                @php($reviewUrl = old('review_url', $settings['review_url'] ?? ''))
                <input id="review_url" class="pm-input" type="url" name="review_url" maxlength="512" aria-describedby="review-url-help"
                       value="{{ is_string($reviewUrl) ? $reviewUrl : '' }}">
                <p id="review-url-help" class="pm-form-note">{{ __('Copia il link da «Ottieni più recensioni» nel Profilo dell’attività su Google.') }}</p>
            </div>

            <fieldset class="pm-settings-section">
                <legend>{{ __('Collegamento Twilio') }}</legend>
                <p class="pm-form-note">{{ __('Aggiungi il mittente WhatsApp al servizio di messaggistica Twilio e abilita la gestione delle rinunce (Advanced Opt-Out) con la parola STOP.') }}</p>

                <div class="pm-form-grid-2">
                    @foreach ([
                        'sender' => 'Numero mittente WhatsApp',
                        'messaging_service_sid' => 'Messaging Service SID',
                        'account_sid' => 'Account SID',
                        'api_key_sid' => 'API Key SID',
                        'content_sid' => 'Content SID del modello approvato',
                    ] as $name => $label)
                        @php($value = old($name, $settings[$name] ?? ''))
                        <div class="pm-form-group">
                            <label for="{{ $name }}" class="pm-label">{{ __($label) }}</label>
                            <input id="{{ $name }}" class="pm-input" type="{{ $name === 'sender' ? 'tel' : 'text' }}" name="{{ $name }}"
                                   value="{{ is_string($value) ? $value : '' }}" maxlength="{{ $name === 'sender' ? 16 : 34 }}" spellcheck="false">
                        </div>
                    @endforeach
                    <div class="pm-form-group">
                        <label for="twilio-api-key-secret" class="pm-label">{{ __('API Key Secret') }}</label>
                        <input id="twilio-api-key-secret" class="pm-input" type="password" name="credentials[api_key_secret]" maxlength="255"
                               autocomplete="new-password" aria-describedby="twilio-secret-help">
                        <p id="twilio-secret-help" class="pm-form-note">{{ $hasSecret ? __('Chiave salvata. Lascia vuoto per conservarla.') : __('Inserisci il segreto della chiave Twilio.') }}</p>
                    </div>
                </div>
            </fieldset>

            <div class="pm-form-group">
                <label for="review-template" class="pm-label">{{ __('Testo del modello') }}</label>
                <textarea id="review-template" class="pm-textarea" rows="8" readonly aria-describedby="review-template-help">{{ __($message) }}</textarea>
                <p id="review-template-help" class="pm-form-note">{{ __('Usa questo testo nel modello da approvare su Twilio. Durante l’invio le variabili vengono sostituite con il nome del parcheggio e il link Google.') }}</p>
            </div>

            <div class="pm-form-actions">
                <button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva impostazioni') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
