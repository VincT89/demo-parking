@props(['record' => null])
@php
    $selectedId = old('customer_id', $record?->customer_id ?? request('customer_id'));
    $selectedCustomer = is_scalar($selectedId) && ctype_digit((string) $selectedId)
        ? \App\Models\Customer::with('vehicles')->find($selectedId) : null;
    if ($selectedCustomer && !$selectedCustomer->is_active && (int) $record?->customer_id !== $selectedCustomer->id) {
        $selectedCustomer = null;
    }
@endphp
<div class="pm-customer-picker" data-customer-picker
    data-url="{{ route('customers.lookup') }}"
    data-autofill="{{ !$record && !session()->hasOldInput() ? 'true' : 'false' }}"
    data-empty-label="{{ __('Nessun cliente trovato.') }}"
    data-error-label="{{ __('Ricerca non disponibile. Riprova.') }}"
    data-min-label="{{ __('Digita almeno due caratteri e scegli un cliente.') }}">
    <input type="hidden" name="customer_id" value="{{ $selectedCustomer?->id }}" data-customer-id>
    @if($record?->customer_id)
        <div class="pm-customer-actions"><span>{{ __('Cliente collegato') }}: {{ $selectedCustomer?->name }}</span><a class="pm-customer-link" href="{{ route('customers.show', $record->customer_id) }}">{{ __('Scheda cliente') }}</a></div>
    @else
        <div class="pm-form-group">
            <label class="pm-label" for="registry-customer-search">{{ __('Cerca cliente in anagrafica') }}</label>
            <input id="registry-customer-search" type="search" autocomplete="off" class="pm-input" data-customer-search value="{{ $selectedCustomer?->name }}" placeholder="{{ __('Nome, email, telefono o targa') }}" aria-describedby="registry-customer-help" aria-controls="registry-customer-results">
            <small id="registry-customer-help" class="pm-field-help">{{ __('Seleziona una scheda per collegare questa operazione allo storico del cliente.') }}</small>
            <div data-customer-results id="registry-customer-results" class="pm-customer-results" hidden></div>
            <span data-customer-feedback role="status" class="pm-field-help"></span>
        </div>
        <div class="pm-customer-actions">
            <button type="button" class="pm-btn pm-btn-secondary pm-btn-sm" data-customer-clear @if(!$selectedCustomer) hidden @endif>{{ __('Rimuovi selezione') }}</button>
            <a class="pm-customer-link" href="{{ route('customers.create') }}" target="_blank" rel="noopener">{{ __('Nuovo cliente (nuova scheda)') }}</a>
        </div>
        <div class="pm-form-group pm-mt-16" data-customer-vehicle-field hidden>
            <label class="pm-label" for="registry-vehicle">{{ __('Targhe associate') }}</label>
            <select id="registry-vehicle" class="pm-select" data-customer-vehicle><option value="">{{ __('Scegli una targa o inseriscila nel modulo') }}</option></select>
        </div>
    @endif
    @if($selectedCustomer)
        <script type="application/json" data-customer-initial>{!! json_encode(['id' => $selectedCustomer->id, 'name' => $selectedCustomer->name, 'email' => $selectedCustomer->email, 'phone' => $selectedCustomer->phone, 'plates' => $selectedCustomer->vehicles->pluck('license_plate')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
</div>
