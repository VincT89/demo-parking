<x-app-layout>
    <x-slot name="header">
        <div><h1 class="pm-page-title">{{ $customer->name }}</h1><div class="pm-page-subtitle">{{ __('Scheda cliente') }} · {{ $customer->type === 'company' ? __('Azienda') : __('Privato') }}</div></div>
        <div class="pm-header-actions"><a href="{{ route('customers.edit', $customer) }}" class="pm-btn pm-btn-primary">{{ __('Modifica cliente') }}</a><a href="{{ route('customers.index') }}" class="pm-btn pm-btn-secondary">{{ __('Torna alla lista') }}</a></div>
    </x-slot>
    <x-flash-message />
    <div class="pm-customer-page">
        @if(!$customer->is_active)<div class="pm-notice pm-notice-info">{{ __('Cliente archiviato. Lo storico resta consultabile.') }}</div>@endif
        <section class="pm-card">
            <h2 class="pm-card-title pm-mb-16">{{ __('Dati cliente') }}</h2>
            <dl class="pm-customer-details">
                @foreach(['email' => 'Email', 'phone' => 'Telefono', 'fiscal_code' => 'Codice fiscale', 'vat_number' => 'Partita IVA', 'recipient_code' => 'Codice destinatario', 'pec' => 'PEC destinatario'] as $field => $label)
                    @if(filled($customer->{$field}))<div><dt>{{ __($label) }}</dt><dd>{{ $field === 'vat_number' ? $customer->vat_country.' '.$customer->{$field} : $customer->{$field} }}</dd></div>@endif
                @endforeach
                @if($customer->address || $customer->city)<div><dt>{{ __('Indirizzo') }}</dt><dd>{{ collect([$customer->address, $customer->postal_code, $customer->city, $customer->province, $customer->country])->filter()->join(', ') }}</dd></div>@endif
                <div><dt>{{ __('Targhe associate') }}</dt><dd>{{ $customer->vehicles->pluck('license_plate')->join(', ') ?: __('Nessuna targa associata.') }}</dd></div>
            </dl>
            @if($customer->notes)<div class="pm-detail-notes"><strong>{{ __('Note interne') }}</strong><p>{{ $customer->notes }}</p></div>@endif
            @if($customer->is_active)
                <div class="pm-customer-actions pm-mt-20">
                    <a class="pm-btn pm-btn-secondary" href="{{ route('reservations.create', ['customer_id' => $customer->id]) }}">{{ __('Nuova prenotazione') }}</a>
                    <a class="pm-btn pm-btn-secondary" href="{{ route('garage.subscriptions.create', ['customer_id' => $customer->id]) }}">{{ __('Nuovo abbonamento') }}</a>
                    <a class="pm-btn pm-btn-secondary" href="{{ route('garage.stays.create', ['customer_id' => $customer->id]) }}">{{ __('Registra ingresso') }}</a>
                    <a class="pm-btn pm-btn-secondary" href="{{ route('customers.records', $customer) }}">{{ __('Collega operazioni esistenti') }}</a>
                </div>
            @endif
        </section>
        <section class="pm-card">
            <h2 class="pm-card-title">{{ __('Storico cliente') }}</h2>
            <p class="pm-card-help">{{ __('Le modifiche all’anagrafica non cambiano i dati delle operazioni già registrate.') }}</p>
            <form method="GET" action="{{ route('customers.show', $customer) }}" class="pm-customer-filters pm-mt-20">
                <div class="pm-form-group"><label for="history-kind" class="pm-label">{{ __('Tipo operazione') }}</label><select id="history-kind" name="kind" class="pm-select"><option value="">{{ __('Tutte le operazioni') }}</option>@foreach(['reservation','subscription','stay','payment','invoice'] as $kind)<option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ \App\Services\CustomerHistoryService::label($kind) }}</option>@endforeach</select></div>
                <div class="pm-form-group"><label for="date_from" class="pm-label">{{ __('Dal') }}</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="pm-input"></div>
                <div class="pm-form-group"><label for="date_to" class="pm-label">{{ __('Al') }}</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="pm-input"></div>
                <div class="pm-customer-actions"><button class="pm-btn pm-btn-primary">{{ __('Filtra') }}</button><a class="pm-btn pm-btn-secondary" href="{{ route('customers.show', $customer) }}">{{ __('Reimposta') }}</a></div>
            </form>
            <div class="pm-table-wrapper pm-mt-20">
                <table class="pm-table pm-responsive-table">
                    <thead><tr><th scope="col">{{ __('Data') }}</th><th scope="col">{{ __('Operazione') }}</th><th scope="col">{{ __('Parcheggio') }}</th><th scope="col">{{ __('Targa') }}</th><th scope="col">{{ __('Importo') }}</th><th scope="col">{{ __('Stato') }}</th><th scope="col">{{ __('Azioni') }}</th></tr></thead>
                    <tbody>
                    @forelse($activities as $activity)
                        <tr>
                            <td data-label="{{ __('Data') }}">{{ \Carbon\Carbon::parse($activity->event_at)->format('d/m/Y H:i') }}</td>
                            <td data-label="{{ __('Operazione') }}"><span class="pm-td-main">{{ $activity->label }}</span><span class="pm-td-sub">{{ $activity->reference ?: '#'.$activity->id }}</span><span class="pm-td-sub">{{ $activity->recorded_name }}</span></td>
                            <td data-label="{{ __('Parcheggio') }}">{{ __($activity->parking_name) }}</td>
                            <td data-label="{{ __('Targa') }}">{{ $activity->license_plate }}</td>
                            <td data-label="{{ __('Importo') }}">@if($activity->amount !== null){{ number_format((float) $activity->amount, 2, ',', '.') }} {{ $activity->currency }}@if($activity->kind === 'subscription')<span class="pm-td-sub">{{ __('al mese') }}</span>@endif @else {{ __('Da determinare') }} @endif</td>
                            <td data-label="{{ __('Stato') }}">{{ $activity->status_label }}</td>
                            <td data-label="{{ __('Azioni') }}"><a href="{{ $activity->url }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Apri') }}</a></td>
                        </tr>
                    @empty
                        <tr class="pm-responsive-empty"><td colspan="7">{{ __('Nessuna operazione collegata per i filtri selezionati.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($activities->hasPages())<div class="pm-pagination">{{ $activities->links() }}</div>@endif
        </section>
        @can('manage-parkings')
            <form method="POST" action="{{ route('customers.status', $customer) }}" class="pm-customer-actions">
                @csrf @method('PATCH')
                <input type="hidden" name="is_active" value="{{ $customer->is_active ? '0' : '1' }}">
                <button class="pm-btn pm-btn-secondary">{{ $customer->is_active ? __('Archivia cliente') : __('Riattiva cliente') }}</button>
                <span class="pm-field-help">{{ __('L’archiviazione mantiene lo storico e nasconde il cliente dai nuovi inserimenti.') }}</span>
            </form>
        @endcan
    </div>
</x-app-layout>
