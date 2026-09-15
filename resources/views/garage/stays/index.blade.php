<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Soste garage') }}</h1>
            <div class="pm-page-subtitle">{{ __('Ingressi giornalieri e veicoli abbonati') }}</div>
        </div>
        <a href="{{ route('garage.stays.create') }}" class="pm-btn pm-btn-primary">{{ __('Registra ingresso') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-mb-16">
        <form method="GET" action="{{ route('garage.stays.index') }}" class="pm-filters">
            <input name="search" value="{{ request('search') }}" class="pm-input" placeholder="{{ __('Cerca cliente, targa o riferimento') }}" aria-label="{{ __('Cerca') }}">
            <select name="parking_id" class="pm-select" aria-label="{{ __('Parcheggio') }}" onchange="this.form.submit()"><option value="">{{ __('Tutti i parcheggi') }}</option>@foreach($parkings as $parking)<option value="{{ $parking->id }}" @selected(request('parking_id') == $parking->id)>{{ __($parking->name) }}</option>@endforeach</select>
            <select name="status" class="pm-select" aria-label="{{ __('Stato') }}" onchange="this.form.submit()"><option value="">{{ __('Tutti gli stati') }}</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            <select name="payment_status" class="pm-select" aria-label="{{ __('Pagamento') }}" onchange="this.form.submit()"><option value="">{{ __('Tutti i pagamenti') }}</option><option value="unpaid" @selected(request('payment_status') === 'unpaid')>{{ __('Da pagare') }}</option><option value="paid" @selected(request('payment_status') === 'paid')>{{ __('Pagato') }}</option></select>
            <button class="pm-btn pm-btn-primary" type="submit">{{ __('Filtra') }}</button>
            <a href="{{ route('garage.stays.index') }}" class="pm-btn pm-btn-secondary">{{ __('Reimposta') }}</a>
        </form>
    </div>

    <div class="pm-card pm-table-card">
        <div class="pm-table-wrap">
            <table class="pm-table pm-responsive-table">
                <thead><tr><th>{{ __('Riferimento') }}</th><th>{{ __('Cliente') }}</th><th>{{ __('Targa') }}</th><th>{{ __('Ingresso') }}</th><th>{{ __('Uscita prevista') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Importo') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead>
                <tbody>
                    @forelse($stays as $stay)
                        @php $statusColor = match($stay->status->value) { 'active' => 'amber', 'completed' => 'green', default => 'gray' }; @endphp
                        <tr>
                            <td data-label="{{ __('Riferimento') }}" class="pm-mono">{{ $stay->reference }}</td>
                            <td data-label="{{ __('Cliente') }}">{{ $stay->customer_name ?: '—' }}</td>
                            <td data-label="{{ __('Targa') }}" class="pm-mono">{{ $stay->license_plate }}</td>
                            <td data-label="{{ __('Ingresso') }}" class="pm-mono">{{ $stay->starts_at->format('d/m/Y H:i') }}</td>
                            <td data-label="{{ __('Uscita prevista') }}" class="pm-mono">{{ $stay->expected_ends_at->format('d/m/Y H:i') }}</td>
                            <td data-label="{{ __('Tipo') }}">{{ $stay->subscription ? __('Abbonamento') : __('Giornaliero') }}</td>
                            <td data-label="{{ __('Importo') }}" class="pm-mono">{{ $stay->subscription ? __('Incluso') : '€ '.number_format((float) ($stay->total_amount ?? $stay->estimated_total), 2, ',', '.') }}</td>
                            <td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $statusColor }}">{{ $stay->status->label() }}</span>@if(!$stay->subscription)<span class="pm-td-sub">{{ $stay->payment_status === 'paid' ? __('Pagato') : __('Da pagare') }}</span>@endif</td>
                            <td data-label="{{ __('Azioni') }}"><a href="{{ route('garage.stays.show', $stay) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Apri') }}</a></td>
                        </tr>
                    @empty<tr class="pm-responsive-empty"><td colspan="9">{{ __('Nessuna sosta trovata.') }}</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($stays->hasPages())<div class="pm-pagination">{{ $stays->links('vendor.pagination.pm') }}</div>@endif
    </div>
</x-app-layout>
