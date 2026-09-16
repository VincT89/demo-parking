<x-app-layout>
    <x-slot name="header">
        <div><h1 class="pm-page-title">{{ __('Clienti') }}</h1><div class="pm-page-subtitle">{{ __('Anagrafica e storico clienti') }}</div></div>
        <a href="{{ route('customers.create') }}" class="pm-btn pm-btn-primary">{{ __('Nuovo cliente') }}</a>
    </x-slot>
    <x-flash-message />
    <div class="pm-customer-page">
        <form method="GET" action="{{ route('customers.index') }}" class="pm-card pm-customer-filters">
            <div class="pm-form-group"><label for="customer-search" class="pm-label">{{ __('Cerca cliente') }}</label><input id="customer-search" name="search" value="{{ request('search') }}" class="pm-input" placeholder="{{ __('Nome, email, telefono o targa') }}"></div>
            <div class="pm-form-group"><label for="customer-type" class="pm-label">{{ __('Tipo cliente') }}</label><select id="customer-type" name="type" class="pm-select"><option value="">{{ __('Tutti') }}</option><option value="person" @selected(request('type') === 'person')>{{ __('Privato') }}</option><option value="company" @selected(request('type') === 'company')>{{ __('Azienda') }}</option></select></div>
            <div class="pm-form-group"><label for="customer-state" class="pm-label">{{ __('Stato') }}</label><select id="customer-state" name="state" class="pm-select"><option value="active" @selected(request('state', 'active') === 'active')>{{ __('Attivi') }}</option><option value="archived" @selected(request('state') === 'archived')>{{ __('Archiviati') }}</option><option value="all" @selected(request('state') === 'all')>{{ __('Tutti') }}</option></select></div>
            <div class="pm-customer-actions"><button class="pm-btn pm-btn-primary">{{ __('Cerca') }}</button><a class="pm-btn pm-btn-secondary" href="{{ route('customers.index') }}">{{ __('Reimposta') }}</a></div>
        </form>
        <div class="pm-card">
            <div class="pm-table-wrapper">
                <table class="pm-table pm-responsive-table">
                    <thead><tr><th scope="col">{{ __('Cliente') }}</th><th scope="col">{{ __('Contatti') }}</th><th scope="col">{{ __('Targhe associate') }}</th><th scope="col">{{ __('Stato') }}</th><th scope="col">{{ __('Azioni') }}</th></tr></thead>
                    <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td data-label="{{ __('Cliente') }}"><a class="pm-customer-link pm-td-main" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a><span class="pm-td-sub">{{ $customer->type === 'company' ? __('Azienda') : __('Privato') }}</span></td>
                            <td data-label="{{ __('Contatti') }}"><span>{{ $customer->email }}</span><span class="pm-td-sub">{{ $customer->phone }}</span></td>
                            <td data-label="{{ __('Targhe associate') }}">{{ $customer->vehicles->pluck('license_plate')->join(', ') }}</td>
                            <td data-label="{{ __('Stato') }}">{{ $customer->is_active ? __('Attivo') : __('Archiviato') }}</td>
                            <td data-label="{{ __('Azioni') }}"><a class="pm-btn pm-btn-secondary pm-btn-sm" href="{{ route('customers.show', $customer) }}">{{ __('Apri scheda') }}</a></td>
                        </tr>
                    @empty
                        <tr class="pm-responsive-empty"><td colspan="5">{{ __('Nessun cliente trovato.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())<div class="pm-pagination">{{ $customers->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
