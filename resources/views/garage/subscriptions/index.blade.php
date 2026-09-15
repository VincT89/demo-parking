<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Abbonamenti') }}</h1>
            <div class="pm-page-subtitle">{{ __('Contratti mensili e posti riservati') }}</div>
        </div>
        <a href="{{ route('garage.subscriptions.create') }}" class="pm-btn pm-btn-primary">{{ __('Nuovo abbonamento') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-mb-16">
        <form method="GET" action="{{ route('garage.subscriptions.index') }}" class="pm-filters">
            <input name="search" value="{{ request('search') }}" class="pm-input" placeholder="{{ __('Cerca cliente, targa o riferimento') }}" aria-label="{{ __('Cerca') }}">
            <select name="parking_id" class="pm-select" aria-label="{{ __('Parcheggio') }}" onchange="this.form.submit()">
                <option value="">{{ __('Tutti i parcheggi') }}</option>
                @foreach ($parkings as $parking)<option value="{{ $parking->id }}" @selected(request('parking_id') == $parking->id)>{{ __($parking->name) }}</option>@endforeach
            </select>
            <select name="status" class="pm-select" aria-label="{{ __('Stato') }}" onchange="this.form.submit()">
                <option value="">{{ __('Tutti gli stati') }}</option>
                @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            <button class="pm-btn pm-btn-primary" type="submit">{{ __('Filtra') }}</button>
            <a href="{{ route('garage.subscriptions.index') }}" class="pm-btn pm-btn-secondary">{{ __('Reimposta') }}</a>
        </form>
    </div>

    <div class="pm-card pm-table-card">
        <div class="pm-table-wrap">
            <table class="pm-table pm-responsive-table">
                <thead><tr><th>{{ __('Riferimento') }}</th><th>{{ __('Cliente') }}</th><th>{{ __('Targa') }}</th><th>{{ __('Parcheggio') }}</th><th>{{ __('Periodo') }}</th><th>{{ __('Pagamento') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        @php
                            $statusColor = match($subscription->status->value) { 'active' => 'green', 'suspended' => 'amber', 'cancelled' => 'red', default => 'gray' };
                            $paid = $subscription->isPaidThroughToday();
                        @endphp
                        <tr>
                            <td data-label="{{ __('Riferimento') }}" class="pm-mono">{{ $subscription->reference }}</td>
                            <td data-label="{{ __('Cliente') }}"><span class="pm-td-main">{{ $subscription->customer_name }}</span><span class="pm-td-sub">{{ $subscription->customer_email ?: '—' }}</span></td>
                            <td data-label="{{ __('Targa') }}" class="pm-mono">{{ $subscription->license_plate }}</td>
                            <td data-label="{{ __('Parcheggio') }}"><span class="pm-td-main">{{ __($subscription->parking->name) }}</span><span class="pm-td-sub">{{ __($subscription->parkingProduct->name) }}</span></td>
                            <td data-label="{{ __('Periodo') }}" class="pm-mono">{{ $subscription->starts_on->format('d/m/Y') }}<br>{{ $subscription->ends_on?->format('d/m/Y') ?? __('Senza scadenza') }}</td>
                            <td data-label="{{ __('Pagamento') }}"><span class="pm-badge {{ $paid ? 'green' : 'amber' }}">{{ $paid ? __('In regola') : __('Da rinnovare') }}</span>@if($subscription->paid_through)<span class="pm-td-sub">{{ __('fino al :date', ['date' => $subscription->paid_through->format('d/m/Y')]) }}</span>@endif</td>
                            <td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $statusColor }}">{{ $subscription->status->label() }}</span></td>
                            <td data-label="{{ __('Azioni') }}"><a href="{{ route('garage.subscriptions.show', $subscription) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Apri') }}</a></td>
                        </tr>
                    @empty
                        <tr class="pm-responsive-empty"><td colspan="8">{{ __('Nessun abbonamento trovato.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscriptions->hasPages())<div class="pm-pagination">{{ $subscriptions->links('vendor.pagination.pm') }}</div>@endif
    </div>
</x-app-layout>
