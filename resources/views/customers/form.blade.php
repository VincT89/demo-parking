<x-app-layout>
    <x-slot name="header">
        <div><h1 class="pm-page-title">{{ $customer->exists ? __('Modifica cliente') : __('Nuovo cliente') }}</h1><div class="pm-page-subtitle">{{ __('Dati condivisi tra prenotazioni e garage') }}</div></div>
        <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
    </x-slot>
    <div class="pm-narrow-page pm-customer-page">
        <x-flash-message />
        <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="pm-form">
            @csrf
            @if($customer->exists) @method('PUT') @endif
            @if($duplicates->isNotEmpty())
                <div class="pm-notice pm-notice-info" role="status">
                    <strong>{{ __('Possibili duplicati') }}</strong>
                    <p>{{ __('Esistono clienti con dati coincidenti. Verifica le schede e conferma se sono persone diverse.') }}</p>
                    <ul>@foreach($duplicates as $duplicate)<li><a class="pm-customer-link" href="{{ route('customers.show', $duplicate) }}" target="_blank" rel="noopener">{{ $duplicate->name }} ({{ $duplicate->email ?: $duplicate->phone }})</a></li>@endforeach</ul>
                    <label class="pm-customer-check"><input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate'))> {{ __('Confermo che si tratta di un cliente distinto.') }}</label>
                </div>
            @endif
            <section class="pm-card">
                <h2 class="pm-card-title pm-mb-16">{{ __('Dati cliente') }}</h2>
                <div class="pm-form-grid-2">
                    <div class="pm-form-group"><label for="type" class="pm-label">{{ __('Tipo cliente') }}</label><select id="type" name="type" class="pm-select"><option value="person" @selected(old('type', $customer->type) === 'person')>{{ __('Privato') }}</option><option value="company" @selected(old('type', $customer->type) === 'company')>{{ __('Azienda') }}</option></select></div>
                    <div class="pm-form-group"><label for="name" class="pm-label pm-label-required">{{ __('Nome o ragione sociale') }}</label><input id="name" name="name" class="pm-input" maxlength="255" value="{{ old('name', $customer->name) }}" required></div>
                    <div class="pm-form-group"><label for="email" class="pm-label">{{ __('Email') }}</label><input id="email" name="email" type="email" class="pm-input" maxlength="255" value="{{ old('email', $customer->email) }}"></div>
                    <div class="pm-form-group"><label for="phone" class="pm-label">{{ __('Telefono') }}</label><input id="phone" name="phone" type="tel" class="pm-input" maxlength="50" value="{{ old('phone', $customer->phone) }}"></div>
                    <div class="pm-form-group pm-form-span-2"><label for="license_plates" class="pm-label">{{ __('Targhe associate') }}</label><textarea id="license_plates" name="license_plates" class="pm-textarea" rows="3" maxlength="1500" aria-describedby="plates-help">{{ old('license_plates', $customer->vehicles->pluck('license_plate')->join("\n")) }}</textarea><small id="plates-help" class="pm-field-help">{{ __('Una targa per riga. Puoi associare più veicoli allo stesso cliente.') }}</small></div>
                </div>
            </section>
            <section class="pm-card">
                <h2 class="pm-card-title">{{ __('Dati di fatturazione') }}</h2>
                <p class="pm-card-help pm-mb-16">{{ __('Facoltativi: saranno proposti quando prepari una fattura.') }}</p>
                <div class="pm-form-grid-2">
                    @foreach(['fiscal_code' => ['Codice fiscale',16], 'vat_number' => ['Partita IVA',28], 'vat_country' => ['Paese IVA',2], 'recipient_code' => ['Codice destinatario',7], 'pec' => ['PEC destinatario',255], 'address' => ['Indirizzo',60], 'postal_code' => ['CAP',10], 'city' => ['Comune',60], 'province' => ['Provincia',2], 'country' => ['Nazione',2]] as $field => [$label, $limit])
                        <div class="pm-form-group"><label for="{{ $field }}" class="pm-label">{{ __($label) }}</label><input id="{{ $field }}" name="{{ $field }}" class="pm-input" type="{{ $field === 'pec' ? 'email' : 'text' }}" maxlength="{{ $limit }}" value="{{ old($field, $customer->{$field}) }}" @required(in_array($field, ['country', 'vat_country']))></div>
                    @endforeach
                </div>
            </section>
            <section class="pm-card">
                <div class="pm-form-group"><label for="notes" class="pm-label">{{ __('Note interne') }}</label><textarea id="notes" name="notes" class="pm-textarea" maxlength="5000">{{ old('notes', $customer->notes) }}</textarea></div>
                <div class="pm-form-actions"><button class="pm-btn pm-btn-primary">{{ __('Salva cliente') }}</button><a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a></div>
            </section>
        </form>
    </div>
</x-app-layout>
