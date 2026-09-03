<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Nuova fattura elettronica') }}</h1>
            <div class="pm-page-subtitle">{{ $reservation->external_id }}</div>
        </div>
        <a class="pm-btn pm-btn-secondary" href="{{ route('reservations.show', $reservation) }}">{{ __('Annulla') }}</a>
    </x-slot>

    <div class="pm-narrow-page pm-animate">
        @if($errors->any())
            <div class="pm-notice pm-notice-danger" role="alert">
                <strong>{{ __('Controlla i dati fiscali inseriti.') }}</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="pm-notice {{ $settings->isSimulator() ? 'pm-notice-info' : 'pm-notice-danger' }}">
            @if($settings->isSimulator())
                <strong>{{ __('Simulazione locale') }}</strong><br>
                {{ __('La bozza e i successivi esiti sono dimostrativi e non hanno valore fiscale.') }}
            @else
                <strong>{{ $settings->invoice_mode === 'aruba_demo' ? __('Aruba DEMO') : __('Aruba produzione') }}</strong><br>
                {{ __('Dopo la conferma potrai trasmettere il documento all’ambiente configurato.') }}
            @endif
        </div>

        <form method="POST" action="{{ route('invoices.store', $reservation) }}" class="pm-form">
            @csrf
            <section class="pm-card">
                <div class="pm-card-header">
                    <div>
                        <h2 class="pm-card-title">{{ __('Dati cliente per la fattura') }}</h2>
                        <p class="pm-card-help">{{ __('Non usare dati inventati per un invio reale.') }}</p>
                    </div>
                </div>

                <div class="pm-form-grid-2">
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_type">{{ __('Tipo cliente') }}</label>
                        <select class="pm-select" id="customer_type" name="customer_type" required>
                            <option value="person" @selected(old('customer_type') === 'person')>{{ __('Privato') }}</option>
                            <option value="company" @selected(old('customer_type') === 'company')>{{ __('Azienda') }}</option>
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_name">{{ __('Nome o ragione sociale') }}</label>
                        <input class="pm-input" id="customer_name" name="customer_name" maxlength="80" value="{{ old('customer_name', $reservation->customer_name) }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_fiscal_code">{{ __('Codice fiscale') }}</label>
                        <input class="pm-input" id="customer_fiscal_code" name="customer_fiscal_code" maxlength="16" value="{{ old('customer_fiscal_code') }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_vat_number">{{ __('Partita IVA') }}</label>
                        <div class="pm-country-input">
                            <input class="pm-input" name="customer_vat_country" aria-label="{{ __('Paese IVA') }}" maxlength="2" value="{{ old('customer_vat_country', 'IT') }}" required>
                            <input class="pm-input" id="customer_vat_number" name="customer_vat_number" value="{{ old('customer_vat_number') }}">
                        </div>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_recipient_code">{{ __('Codice destinatario') }}</label>
                        <input class="pm-input" id="customer_recipient_code" name="customer_recipient_code" maxlength="7" value="{{ old('customer_recipient_code') }}" placeholder="0000000">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_pec">{{ __('PEC destinatario') }}</label>
                        <input class="pm-input" id="customer_pec" name="customer_pec" type="email" value="{{ old('customer_pec') }}">
                    </div>
                    <div class="pm-form-group pm-form-span-2">
                        <label class="pm-label" for="customer_address">{{ __('Indirizzo') }}</label>
                        <input class="pm-input" id="customer_address" name="customer_address" maxlength="60" value="{{ old('customer_address') }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_postal_code">{{ __('CAP') }}</label>
                        <input class="pm-input" id="customer_postal_code" name="customer_postal_code" value="{{ old('customer_postal_code') }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_city">{{ __('Comune') }}</label>
                        <input class="pm-input" id="customer_city" name="customer_city" value="{{ old('customer_city') }}" required>
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_province">{{ __('Provincia') }}</label>
                        <input class="pm-input" id="customer_province" name="customer_province" maxlength="2" value="{{ old('customer_province') }}">
                    </div>
                    <div class="pm-form-group">
                        <label class="pm-label" for="customer_country">{{ __('Nazione') }}</label>
                        <input class="pm-input" id="customer_country" name="customer_country" maxlength="2" value="{{ old('customer_country', 'IT') }}" required>
                    </div>
                </div>
            </section>

            <section class="pm-card pm-invoice-total-card">
                <div><span>{{ __('Prenotazione') }}</span><strong>{{ $reservation->external_id }}</strong></div>
                <div><span>{{ __('Totale pratica') }}</span><strong>€ {{ number_format((float) $reservation->price, 2, ',', '.') }}</strong></div>
                <div><span>{{ __('IVA configurata') }}</span><strong>{{ number_format((float) $settings->default_vat_rate, 2, ',', '.') }}%</strong></div>
            </section>

            <div class="pm-form-actions pm-form-actions-end">
                <a class="pm-btn pm-btn-secondary" href="{{ route('reservations.show', $reservation) }}">{{ __('Annulla') }}</a>
                <button class="pm-btn pm-btn-primary" type="submit">{{ __('Crea bozza') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
