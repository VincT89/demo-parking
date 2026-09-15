<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Garage') }}</h1>
            <div class="pm-page-subtitle">{{ __('Abbonamenti, ingressi giornalieri e incassi') }}</div>
        </div>
        <div class="pm-header-actions">
            <a href="{{ route('garage.stays.create') }}" class="pm-btn pm-btn-primary">{{ __('Registra ingresso') }}</a>
            <a href="{{ route('garage.subscriptions.create') }}" class="pm-btn pm-btn-secondary">{{ __('Nuovo abbonamento') }}</a>
        </div>
    </x-slot>

    <x-flash-message />

    <div class="pm-garage-page">
        <div class="pm-card pm-settings-selector pm-mb-16">
            <form method="GET" action="{{ route('garage.index') }}" class="pm-inline-filter">
                <div class="pm-form-group">
                    <label for="garage_parking_id" class="pm-label">{{ __('Parcheggio') }}</label>
                    <select id="garage_parking_id" name="parking_id" class="pm-select" onchange="this.form.submit()">
                        @foreach ($parkings as $item)
                            <option value="{{ $item->id }}" @selected($item->is($parking))>{{ __($item->name) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <div class="pm-stats-grid pm-garage-stats">
            <div class="pm-stat">
                <div class="pm-stat-label">{{ __('Abbonamenti attivi') }}</div>
                <div class="pm-stat-value blue">{{ $stats['active_subscriptions'] }}</div>
                <div class="pm-stat-delta">{{ trans_choice(':count posto riservato|:count posti riservati', $stats['reserved_subscription_spots'], ['count' => $stats['reserved_subscription_spots']]) }}</div>
            </div>
            <div class="pm-stat">
                <div class="pm-stat-label">{{ __('Veicoli presenti') }}</div>
                <div class="pm-stat-value amber">{{ $stats['active_stays'] }}</div>
                <div class="pm-stat-delta">{{ __('abbonati e giornalieri') }}</div>
            </div>
            <div class="pm-stat">
                <div class="pm-stat-label">{{ __('Soste da incassare') }}</div>
                <div class="pm-stat-value red">{{ $stats['unpaid_stays'] }}</div>
                <div class="pm-stat-delta">{{ __('escluse le soste in abbonamento') }}</div>
            </div>
            <div class="pm-stat">
                <div class="pm-stat-label">{{ __('Incassi del mese') }}</div>
                <div class="pm-stat-value green">€ {{ number_format((float) $stats['month_payments'], 2, ',', '.') }}</div>
                <div class="pm-stat-delta">{{ __('pagamenti garage confermati') }}</div>
            </div>
        </div>

        <div class="pm-garage-shortcuts pm-mb-16">
            <a href="{{ route('garage.subscriptions.index', ['parking_id' => $parking->id]) }}" class="pm-card pm-garage-shortcut">
                <strong>{{ __('Gestisci abbonamenti') }}</strong>
                <span>{{ __('Scadenze, rinnovi, pagamenti e targhe') }}</span>
            </a>
            <a href="{{ route('garage.stays.index', ['parking_id' => $parking->id]) }}" class="pm-card pm-garage-shortcut">
                <strong>{{ __('Gestisci soste') }}</strong>
                <span>{{ __('Ingressi, uscite e pagamenti al banco') }}</span>
            </a>
            @can('manage-parkings')
                <a href="{{ route('garage.rates.index', ['parking_id' => $parking->id]) }}" class="pm-card pm-garage-shortcut">
                    <strong>{{ __('Configura tariffe') }}</strong>
                    <span>{{ __('Prezzi mensili e giornalieri del garage') }}</span>
                </a>
            @endcan
        </div>

        <div class="pm-grid-2 pm-garage-lists">
            <section class="pm-card">
                <div class="pm-card-header">
                    <h2 class="pm-card-title">{{ __('Ultimi abbonamenti') }}</h2>
                    <a href="{{ route('garage.subscriptions.index', ['parking_id' => $parking->id]) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Vedi tutti') }}</a>
                </div>
                <div class="pm-table-wrapper">
                    <table class="pm-table pm-responsive-table">
                        <thead><tr><th>{{ __('Cliente') }}</th><th>{{ __('Targa') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead>
                        <tbody>
                            @forelse ($latestSubscriptions as $subscription)
                                <tr>
                                    <td data-label="{{ __('Cliente') }}"><span class="pm-td-main">{{ $subscription->customer_name }}</span><span class="pm-td-sub">{{ $subscription->reference }}</span></td>
                                    <td data-label="{{ __('Targa') }}" class="pm-mono">{{ $subscription->license_plate }}</td>
                                    <td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $subscription->status->value === 'active' ? 'green' : 'gray' }}">{{ $subscription->status->label() }}</span></td>
                                    <td data-label="{{ __('Azioni') }}"><a href="{{ route('garage.subscriptions.show', $subscription) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Apri') }}</a></td>
                                </tr>
                            @empty
                                <tr class="pm-responsive-empty"><td colspan="4">{{ __('Nessun abbonamento presente.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="pm-card">
                <div class="pm-card-header">
                    <h2 class="pm-card-title">{{ __('Ultimi ingressi') }}</h2>
                    <a href="{{ route('garage.stays.index', ['parking_id' => $parking->id]) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Vedi tutti') }}</a>
                </div>
                <div class="pm-table-wrapper">
                    <table class="pm-table pm-responsive-table">
                        <thead><tr><th>{{ __('Targa') }}</th><th>{{ __('Ingresso') }}</th><th>{{ __('Tipo') }}</th><th>{{ __('Azioni') }}</th></tr></thead>
                        <tbody>
                            @forelse ($latestStays as $stay)
                                <tr>
                                    <td data-label="{{ __('Targa') }}"><span class="pm-td-main pm-mono">{{ $stay->license_plate }}</span><span class="pm-td-sub">{{ $stay->reference }}</span></td>
                                    <td data-label="{{ __('Ingresso') }}" class="pm-mono">{{ $stay->starts_at->format('d/m/Y H:i') }}</td>
                                    <td data-label="{{ __('Tipo') }}">{{ $stay->subscription ? __('Abbonamento') : __('Giornaliero') }}</td>
                                    <td data-label="{{ __('Azioni') }}"><a href="{{ route('garage.stays.show', $stay) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Apri') }}</a></td>
                                </tr>
                            @empty
                                <tr class="pm-responsive-empty"><td colspan="4">{{ __('Nessun ingresso presente.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
