<x-app-layout>
    <x-slot name="header">
        <div><h1 class="pm-page-title">{{ __('Collega operazioni esistenti') }}</h1><div class="pm-page-subtitle">{{ $customer->name }}</div></div>
        <a href="{{ route('customers.show', $customer) }}" class="pm-btn pm-btn-secondary">{{ __('Scheda cliente') }}</a>
    </x-slot>
    <x-flash-message />
    <div class="pm-customer-page">
        <div class="pm-notice pm-notice-info">{{ __('Cerca e verifica ogni operazione prima di collegarla. I dati originali non verranno modificati.') }}</div>
        <form method="GET" action="{{ route('customers.records', $customer) }}" class="pm-card pm-customer-filters">
            <div class="pm-form-group"><label for="records-search" class="pm-label">{{ __('Cerca') }}</label><input id="records-search" name="search" class="pm-input" value="{{ $search }}" placeholder="{{ __('Nome, email, telefono o targa') }}"></div>
            <div class="pm-form-group"><label for="records-kind" class="pm-label">{{ __('Tipo operazione') }}</label><select id="records-kind" name="kind" class="pm-select">@foreach(['reservation','subscription','stay'] as $option)<option value="{{ $option }}" @selected($kind === $option)>{{ \App\Services\CustomerHistoryService::label($option) }}</option>@endforeach</select></div>
            <button class="pm-btn pm-btn-primary">{{ __('Cerca') }}</button>
        </form>
        <section class="pm-card">
            <div class="pm-table-wrapper">
                <table class="pm-table pm-responsive-table">
                    <thead><tr><th scope="col">{{ __('Cliente') }}</th><th scope="col">{{ __('Data') }}</th><th scope="col">{{ __('Parcheggio') }}</th><th scope="col">{{ __('Targa') }}</th><th scope="col">{{ __('Azioni') }}</th></tr></thead>
                    <tbody>
                    @forelse($records as $record)
                        @php
                            $detailRoute = match($kind) { 'reservation' => 'reservations.show', 'subscription' => 'garage.subscriptions.show', 'stay' => 'garage.stays.show' };
                        @endphp
                        <tr>
                            <td data-label="{{ __('Cliente') }}">{{ $record->customer_name }}<span class="pm-td-sub">{{ $record->customer_email }}</span><span class="pm-td-sub">{{ $record->customer_phone }}</span><span class="pm-td-sub">{{ $record->reference ?? $record->external_id }}</span></td>
                            <td data-label="{{ __('Data') }}">{{ ($record->starts_at ?? $record->starts_on)->format('d/m/Y H:i') }}</td>
                            <td data-label="{{ __('Parcheggio') }}">{{ __($record->parking->name) }}</td>
                            <td data-label="{{ __('Targa') }}">{{ $record->license_plate }}</td>
                            <td data-label="{{ __('Azioni') }}">
                                <div class="pm-customer-actions">
                                    <a href="{{ route($detailRoute, $record) }}" class="pm-btn pm-btn-secondary pm-btn-sm" target="_blank" rel="noopener">{{ __('Apri') }}</a>
                                    @if($customer->is_active)
                                        <form method="POST" action="{{ route('customers.records.link', $customer) }}">
                                            @csrf
                                            <input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="record_id" value="{{ $record->id }}">
                                            <button class="pm-btn pm-btn-primary pm-btn-sm">{{ __('Collega') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="pm-responsive-empty"><td colspan="5">{{ __('Nessuna operazione da collegare trovata.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($records->hasPages())<div class="pm-pagination">{{ $records->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
