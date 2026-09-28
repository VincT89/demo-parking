    @if(in_array($platform->slug, ['parkos', 'parking-my-car', 'vologio']))
        <div class="pm-card" style="max-width:720px;padding:20px;margin-top:24px">
            <h2 class="pm-card-title">{{ __('Connessione alla piattaforma') }}</h2>
            @if(config('demo.enabled'))
                <p class="pm-form-note">{{ __('Le connessioni di questa demo sono simulate. Non inserire credenziali reali.') }}</p>
            @elseif($platform->uses_legacy_credentials)
                <p style="margin-top:12px">{{ __('Questa connessione utilizza le credenziali già configurate. Per modificarle rivolgiti al responsabile della connessione.') }}</p>
            @else
                <p style="margin:12px 0">{{ $platform->connection_settings ? __('Connessione configurata. Lascia vuoti i campi che vuoi mantenere.') : __('Configura l’account della piattaforma per questa gestione.') }}</p>
                <form method="POST" action="{{ route('platforms.connection', $platform) }}" class="pm-form">@csrf @parkingContext @method('PUT')
                    @foreach($platform->slug === 'vologio' ? ['api_key' => 'Chiave API', 'client_id' => 'Client ID'] : ['client_id' => 'Client ID', 'client_secret' => 'Client secret', 'username' => 'Nome utente', 'password' => 'Password'] as $credential => $label)
                        <div class="pm-form-group"><label class="pm-label" for="credential-{{ $credential }}">{{ __($label) }}</label><input type="password" class="pm-input" id="credential-{{ $credential }}" name="credentials[{{ $credential }}]" autocomplete="new-password" maxlength="2000">@error('credentials.'.$credential)<div role="alert">{{ $message }}</div>@enderror</div>
                    @endforeach
                    <button type="submit" class="pm-btn pm-btn-primary">{{ __('Salva connessione') }}</button>
                </form>
            @endif
        </div>
    @endif
    @if(!$platform->uses_legacy_credentials && in_array($platform->slug, ['parkos', 'parking-my-car', 'vologio']))
        @can('manage-shared-connections')
            <div class="pm-card" style="max-width:720px;padding:20px;margin-top:24px">
                <form method="POST" action="{{ route('platforms.connection', $platform) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('Utilizzare l’account della piattaforma già configurato? Verifica che includa il nuovo parcheggio.') }}">@csrf @parkingContext @method('PUT')
                    <input type="hidden" name="use_shared_connection" value="1">
                    <p style="margin-bottom:12px">{{ __('Puoi utilizzare l’account esistente se comprende anche questo parcheggio.') }}</p><button type="submit" class="pm-btn pm-btn-secondary">{{ __('Utilizza la connessione esistente') }}</button>
                </form>
            </div>
        @endcan
    @endif
    @foreach($platform->listings as $linkedListing)
        <div class="pm-card" style="max-width:720px;padding:20px;margin-top:24px">
            <form method="POST" action="{{ route('platforms.listing.update', [$platform, $linkedListing]) }}" class="pm-form">@csrf @parkingContext @method('PUT')
                <label class="pm-label" for="external-{{ $linkedListing->id }}">{{ __('Codice del parcheggio su :platform', ['platform' => $platform->name]) }}</label>
                <p id="external-id-help-{{ $linkedListing->id }}" class="pm-form-note">{{ __('Inserisci il codice assegnato dalla piattaforma a questo parcheggio.') }}</p>
                <input class="pm-input" aria-describedby="external-id-help-{{ $linkedListing->id }}" id="external-{{ $linkedListing->id }}" name="external_id" value="{{ $linkedListing->external_id }}" maxlength="255" @readonly($platform->uses_legacy_credentials && !auth()->user()->can('manage-shared-connections'))>
                <label><input type="checkbox" name="is_active" value="1" @checked($linkedListing->is_active)>{{ __('Collegamento attivo') }}</label>
                @if(!$platform->uses_legacy_credentials || auth()->user()->can('manage-shared-connections'))<button type="submit" class="pm-btn pm-btn-secondary">{{ __('Salva collegamento') }}</button>@endif
            </form>
        </div>
    @endforeach
