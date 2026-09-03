<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Fatturazione e stampa') }}</h1>
            <div class="pm-page-subtitle">{{ __('Configurazione per parcheggio') }}</div>
        </div>
    </x-slot>

    <div class="pm-settings-page pm-animate">
        <x-flash-message />

        @if ($errors->any())
            <div class="pm-notice pm-notice-danger" role="alert">
                <strong>{{ __('Controlla i campi evidenziati.') }}</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="pm-card pm-settings-selector">
            <form method="GET" action="{{ route('operational-settings.edit') }}" class="pm-inline-filter">
                <div class="pm-form-group">
                    <label class="pm-label" for="parking_id">{{ __('Parcheggio') }}</label>
                    <select class="pm-select" id="parking_id" name="parking_id" onchange="this.form.submit()">
                        @foreach ($parkings as $item)
                            <option value="{{ $item->id }}" @selected($item->is($parking))>{{ __($item->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button class="pm-btn pm-btn-secondary" type="submit">{{ __('Carica') }}</button></noscript>
            </form>
        </div>

        <form method="POST" action="{{ route('operational-settings.update', $parking) }}" class="pm-settings-form">
            @csrf
            @method('PUT')

            <section class="pm-card">
                <div class="pm-card-header pm-settings-card-header">
                    <div>
                        <h2 class="pm-card-title">{{ __('Fatturazione elettronica Aruba') }}</h2>
                        <p class="pm-card-help">{{ __('Scegli il simulatore interno oppure un ambiente ufficiale Aruba.') }}</p>
                    </div>
                </div>

                <div class="pm-form-grid-2">
                    <div class="pm-form-group">
                        <label class="pm-label" for="invoice_mode">{{ __('Modalità') }}</label>
                        <select class="pm-select" id="invoice_mode" name="invoice_mode" required>
                            <option value="simulator" @selected(old('invoice_mode', $settings->invoice_mode) === 'simulator')>{{ __('Simulatore locale (nessun invio reale)') }}</option>
                            <option value="aruba_demo" @selected(old('invoice_mode', $settings->invoice_mode) === 'aruba_demo')>{{ __('Aruba DEMO (richiede accreditamento)') }}</option>
                            <option value="aruba_production" @selected(old('invoice_mode', $settings->invoice_mode) === 'aruba_production')>{{ __('Aruba produzione') }}</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="default_vat_rate">{{ __('Aliquota IVA predefinita') }}</label>
                        <div class="pm-input-suffix">
                            <input class="pm-input" id="default_vat_rate" name="default_vat_rate" type="number" min="0.01" max="100" step="0.01" value="{{ old('default_vat_rate', $settings->default_vat_rate) }}" required>
                            <span>%</span>
                        </div>
                    </div>
                </div>

                <div id="aruba-credentials" class="pm-settings-subsection">
                    <h3>{{ __('Credenziali API') }}</h3>
                    <p class="pm-card-help">{{ __('Le credenziali restano cifrate. Lascia i campi vuoti per mantenere quelle già salvate.') }}</p>
                    <div class="pm-form-grid-2">
                        <div class="pm-form-group">
                            <label class="pm-label" for="aruba_username">{{ __('Username Aruba') }}</label>
                            <input class="pm-input" id="aruba_username" name="aruba_username" autocomplete="off" value="{{ old('aruba_username') }}" placeholder="{{ $settings->aruba_username ? __('Configurato') : '' }}">
                        </div>
                        <div class="pm-form-group">
                            <label class="pm-label" for="aruba_password">{{ __('Password Aruba') }}</label>
                            <div class="pm-password-field">
                                <input class="pm-input pm-password-input" id="aruba_password" name="aruba_password" type="password" autocomplete="new-password" placeholder="{{ $settings->aruba_password ? __('Configurata') : '' }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pm-notice pm-notice-info" id="invoice-mode-note">
                    {{ __('Il simulatore genera chiamate ed esiti dimostrativi, ma nessun documento viene inviato ad Aruba o a SdI.') }}
                </div>
            </section>

            <section class="pm-card">
                <div class="pm-card-header pm-settings-card-header">
                    <div>
                        <h2 class="pm-card-title">{{ __('Dati dell’emittente e numerazione') }}</h2>
                        <p class="pm-card-help">{{ __('In modalità Aruba questi dati devono coincidere con l’anagrafica fiscale abilitata sul servizio.') }}</p>
                    </div>
                </div>

                <div class="pm-form-grid-2">
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_business_name">{{ __('Ragione sociale emittente') }}</label>
                        <input class="pm-input" id="issuer_business_name" name="issuer_business_name" value="{{ old('issuer_business_name', $settings->issuer_business_name) }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_vat_number">{{ __('Partita IVA emittente') }}</label>
                        <div class="pm-country-input">
                            <input class="pm-input" name="issuer_vat_country" aria-label="{{ __('Paese IVA') }}" maxlength="2" value="{{ old('issuer_vat_country', $settings->issuer_vat_country) }}" required>
                            <input class="pm-input" id="issuer_vat_number" name="issuer_vat_number" inputmode="numeric" value="{{ old('issuer_vat_number', $settings->issuer_vat_number) }}">
                        </div>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_fiscal_code">{{ __('Codice fiscale emittente') }}</label>
                        <input class="pm-input" id="issuer_fiscal_code" name="issuer_fiscal_code" maxlength="16" value="{{ old('issuer_fiscal_code', $settings->issuer_fiscal_code) }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_tax_regime">{{ __('Regime fiscale') }}</label>
                        <input class="pm-input" id="issuer_tax_regime" name="issuer_tax_regime" maxlength="4" value="{{ old('issuer_tax_regime', $settings->issuer_tax_regime) }}" required>
                    </div>
                    <div class="pm-form-group pm-form-span-2">
                        <label class="pm-label" for="issuer_address">{{ __('Indirizzo emittente') }}</label>
                        <input class="pm-input" id="issuer_address" name="issuer_address" value="{{ old('issuer_address', $settings->issuer_address) }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_postal_code">{{ __('CAP') }}</label>
                        <input class="pm-input" id="issuer_postal_code" name="issuer_postal_code" inputmode="numeric" value="{{ old('issuer_postal_code', $settings->issuer_postal_code) }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_city">{{ __('Comune') }}</label>
                        <input class="pm-input" id="issuer_city" name="issuer_city" value="{{ old('issuer_city', $settings->issuer_city) }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_province">{{ __('Provincia') }}</label>
                        <input class="pm-input" id="issuer_province" name="issuer_province" maxlength="2" value="{{ old('issuer_province', $settings->issuer_province) }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="issuer_country">{{ __('Nazione') }}</label>
                        <input class="pm-input" id="issuer_country" name="issuer_country" maxlength="2" value="{{ old('issuer_country', $settings->issuer_country) }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="invoice_prefix">{{ __('Prefisso numerazione') }}</label>
                        <input class="pm-input" id="invoice_prefix" name="invoice_prefix" maxlength="12" value="{{ old('invoice_prefix', $settings->invoice_prefix) }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="next_invoice_number">{{ __('Prossimo progressivo') }}</label>
                        <input class="pm-input" id="next_invoice_number" name="next_invoice_number" type="number" min="1" value="{{ old('next_invoice_number', $settings->next_invoice_number) }}" required>
                    </div>
                </div>

                <input type="hidden" name="prices_include_vat" value="0">
                <label class="pm-switch-row" for="prices_include_vat">
                    <input class="pm-checkbox" id="prices_include_vat" name="prices_include_vat" type="checkbox" value="1" @checked(old('prices_include_vat', $settings->prices_include_vat))>
                    <span>
                        <strong>{{ __('I prezzi delle prenotazioni includono già l’IVA') }}</strong>
                        <small>{{ __('Il gestionale scorpora imponibile e IVA dal totale prenotazione.') }}</small>
                    </span>
                </label>
            </section>

            <section class="pm-card">
                <div class="pm-card-header pm-settings-card-header">
                    <div>
                        <h2 class="pm-card-title">{{ __('Biglietto di ritiro') }}</h2>
                        <p class="pm-card-help">{{ __('Formato predefinito per il biglietto consegnato al cliente.') }}</p>
                    </div>
                </div>

                <div class="pm-form-grid-2">
                    <div class="pm-form-group">
                        <label class="pm-label" for="ticket_format">{{ __('Formato foglio') }}</label>
                        <select class="pm-select" id="ticket_format" name="ticket_format" required>
                            <option value="a4" @selected(old('ticket_format', $settings->ticket_format) === 'a4')>A4</option>
                            <option value="a6" @selected(old('ticket_format', $settings->ticket_format) === 'a6')>A6</option>
                            <option value="label" @selected(old('ticket_format', $settings->ticket_format) === 'label')>{{ __('Etichetta') }}</option>
                            <option value="custom" @selected(old('ticket_format', $settings->ticket_format) === 'custom')>{{ __('Personalizzato') }}</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="ticket_orientation">{{ __('Orientamento') }}</label>
                        <select class="pm-select" id="ticket_orientation" name="ticket_orientation" required>
                            <option value="portrait" @selected(old('ticket_orientation', $settings->ticket_orientation) === 'portrait')>{{ __('Verticale') }}</option>
                            <option value="landscape" @selected(old('ticket_orientation', $settings->ticket_orientation) === 'landscape')>{{ __('Orizzontale') }}</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="ticket_width_mm">{{ __('Larghezza etichetta/personalizzato (mm)') }}</label>
                        <input class="pm-input" id="ticket_width_mm" name="ticket_width_mm" type="number" min="30" max="216" value="{{ old('ticket_width_mm', $settings->ticket_width_mm) }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="ticket_height_mm">{{ __('Altezza etichetta/personalizzato (mm)') }}</label>
                        <input class="pm-input" id="ticket_height_mm" name="ticket_height_mm" type="number" min="30" max="356" value="{{ old('ticket_height_mm', $settings->ticket_height_mm) }}" required>
                    </div>
                    <div class="pm-form-group pm-form-span-2">
                        <label class="pm-label" for="ticket_title">{{ __('Titolo biglietto') }}</label>
                        <input class="pm-input" id="ticket_title" name="ticket_title" maxlength="80" value="{{ old('ticket_title', __($settings->ticket_title)) }}" required>
                    </div>
                    <div class="pm-form-group pm-form-span-2">
                        <label class="pm-label" for="ticket_footer">{{ __('Nota a piè di pagina') }}</label>
                        <textarea class="pm-textarea" id="ticket_footer" name="ticket_footer" maxlength="500">{{ old('ticket_footer', $settings->ticket_footer ? __($settings->ticket_footer) : '') }}</textarea>
                    </div>
                </div>

                <div class="pm-switch-stack">
                    <input type="hidden" name="ticket_auto_open_on_exit" value="0">
                    <label class="pm-switch-row" for="ticket_auto_open_on_exit">
                        <input class="pm-checkbox" id="ticket_auto_open_on_exit" name="ticket_auto_open_on_exit" type="checkbox" value="1" @checked(old('ticket_auto_open_on_exit', $settings->ticket_auto_open_on_exit))>
                        <span><strong>{{ __('Apri la stampa quando viene registrata l’uscita') }}</strong><small>{{ __('Il browser mostrerà il proprio dialogo di stampa.') }}</small></span>
                    </label>

                    <input type="hidden" name="ticket_show_logo" value="0">
                    <label class="pm-switch-row" for="ticket_show_logo">
                        <input class="pm-checkbox" id="ticket_show_logo" name="ticket_show_logo" type="checkbox" value="1" @checked(old('ticket_show_logo', $settings->ticket_show_logo))>
                        <span><strong>{{ __('Mostra il logo sul biglietto') }}</strong></span>
                    </label>
                </div>
            </section>

            <div class="pm-settings-savebar">
                <span>{{ __('Le impostazioni valgono per :parking.', ['parking' => __($parking->name)]) }}</span>
                <button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva configurazione') }}</button>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const mode = document.getElementById('invoice_mode');
            const credentials = document.getElementById('aruba-credentials');
            const note = document.getElementById('invoice-mode-note');
            const messages = {
                simulator: @js(__('Il simulatore genera chiamate ed esiti dimostrativi, ma nessun documento viene inviato ad Aruba o a SdI.')),
                aruba_demo: @js(__('L’ambiente DEMO Aruba richiede credenziali temporanee rilasciate dopo l’accreditamento.')),
                aruba_production: @js(__('Attenzione: in produzione l’azione di invio trasmette realmente il documento ad Aruba e a SdI.')),
            };
            const sync = () => {
                const simulated = mode.value === 'simulator';
                credentials.hidden = simulated;
                note.textContent = messages[mode.value];
                note.classList.toggle('pm-notice-danger', mode.value === 'aruba_production');
                note.classList.toggle('pm-notice-info', mode.value !== 'aruba_production');
            };
            mode.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-app-layout>
