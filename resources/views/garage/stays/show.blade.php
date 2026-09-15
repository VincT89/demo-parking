@php
    $statusColor = match($stay->status->value) { 'active' => 'amber', 'completed' => 'green', default => 'gray' };
    $amount = $stay->total_amount ?? $stay->estimated_total;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Dettaglio sosta') }}</h1>
            <div class="pm-page-subtitle">{{ $stay->reference }}</div>
        </div>
        <div class="pm-header-actions">
            <a href="{{ route('garage.stays.ticket', $stay) }}" class="pm-btn pm-btn-secondary">{{ __('Stampa biglietto') }}</a>
            <a href="{{ route('garage.stays.index') }}" class="pm-btn pm-btn-secondary">{{ __('Elenco') }}</a>
            <a href="{{ route('garage.stays.create') }}" class="pm-btn pm-btn-primary">{{ __('Nuovo ingresso') }}</a>
        </div>
    </x-slot>

    <x-flash-message />

    <div class="pm-garage-detail">
        <div class="pm-garage-detail-grid">
            <section class="pm-card">
                <div class="pm-card-header">
                    <div><h2 class="pm-card-title pm-mono">{{ $stay->license_plate }}</h2><p class="pm-card-help">{{ $stay->customer_name ?: __('Cliente non indicato') }}</p></div>
                    <span class="pm-badge {{ $statusColor }}">{{ $stay->status->label() }}</span>
                </div>
                <dl class="pm-definition-grid">
                    <div><dt>{{ __('Parcheggio') }}</dt><dd>{{ __($stay->parking->name) }}</dd></div>
                    <div><dt>{{ __('Categoria') }}</dt><dd>{{ __($stay->parkingProduct->name) }}</dd></div>
                    <div><dt>{{ __('Tipo') }}</dt><dd>{{ $stay->subscription ? __('Abbonamento') : __('Giornaliero') }}</dd></div>
                    <div><dt>{{ __('Tariffa') }}</dt><dd>{{ $stay->subscription ? __('Inclusa nell’abbonamento') : ($stay->rate ? __($stay->rate->name) : __('Tariffa archiviata')) }}</dd></div>
                    <div><dt>{{ __('Ingresso') }}</dt><dd>{{ $stay->starts_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>{{ __('Uscita prevista') }}</dt><dd>{{ $stay->expected_ends_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>{{ __('Uscita effettiva') }}</dt><dd>{{ $stay->ended_at?->format('d/m/Y H:i') ?? __('Veicolo ancora presente') }}</dd></div>
                    <div><dt>{{ __('Importo') }}</dt><dd>{{ $stay->subscription ? __('Incluso') : '€ '.number_format((float) $amount, 2, ',', '.') }}</dd></div>
                    @if($stay->subscription)<div class="pm-definition-total"><dt>{{ __('Abbonamento collegato') }}</dt><dd><a href="{{ route('garage.subscriptions.show', $stay->subscription) }}">{{ $stay->subscription->reference }}</a></dd></div>@endif
                </dl>
                @if($stay->notes)<div class="pm-detail-notes"><strong>{{ __('Note') }}</strong><p>{{ $stay->notes }}</p></div>@endif
            </section>

            <aside class="pm-gap">
                @if($stay->status->value === 'active')
                    <section class="pm-card">
                        <h2 class="pm-card-title">{{ __('Registra uscita') }}</h2>
                        <p class="pm-card-help">{{ __('L’importo della sosta giornaliera viene ricalcolato sull’orario effettivo.') }}</p>
                        <form method="POST" action="{{ route('garage.stays.checkout', $stay) }}" class="pm-form pm-mt-20">
                            @csrf
                            <div class="pm-form-group"><label for="ended_at" class="pm-label pm-label-required">{{ __('Data e ora uscita') }}</label><input id="ended_at" name="ended_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" min="{{ $stay->starts_at->copy()->addMinute()->format('Y-m-d\TH:i') }}" class="pm-input" required></div>
                            <button type="submit" class="pm-btn pm-btn-primary pm-btn-block">{{ __('Conferma uscita') }}</button>
                        </form>
                    </section>
                @endif

                @if(!$stay->subscription && $stay->status->value === 'completed' && $stay->payment_status !== 'paid')
                    <section class="pm-card">
                        <h2 class="pm-card-title">{{ __('Registra pagamento') }}</h2>
                        <p class="pm-card-help">{{ __('Inserisci il metodo realmente usato al banco.') }}</p>
                        <form method="POST" action="{{ route('garage.stays.payments.store', $stay) }}" class="pm-form pm-mt-20">
                            @csrf
                            <div class="pm-form-group"><label for="stay_payment_method" class="pm-label">{{ __('Metodo') }}</label><select id="stay_payment_method" name="method" class="pm-select">@foreach($paymentMethods as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></div>
                            <div class="pm-form-group"><label for="stay_payment_amount" class="pm-label">{{ __('Importo') }}</label><div class="pm-input-suffix"><input id="stay_payment_amount" name="amount" type="number" min="0.01" step="0.01" value="{{ $stay->total_amount }}" class="pm-input" required><span>€</span></div></div>
                            <div class="pm-form-group"><label for="stay_payment_date" class="pm-label">{{ __('Data pagamento') }}</label><input id="stay_payment_date" name="paid_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" class="pm-input" required></div>
                            <button type="submit" class="pm-btn pm-btn-primary pm-btn-block">{{ __('Segna come pagato') }}</button>
                        </form>
                    </section>
                @endif

                @if($stay->subscription)
                    <div class="pm-notice pm-notice-info">{{ __('Questa presenza non riduce nuovamente la disponibilità: il posto è già riservato dall’abbonamento.') }}</div>
                @endif
            </aside>
        </div>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header"><h2 class="pm-card-title">{{ __('Storico pagamenti') }}</h2></div>
            <div class="pm-table-wrapper"><table class="pm-table pm-responsive-table"><thead><tr><th>{{ __('Data') }}</th><th>{{ __('Metodo') }}</th><th>{{ __('Importo') }}</th><th>{{ __('Operatore') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead><tbody>
                @forelse($stay->payments as $payment)
                    <tr><td data-label="{{ __('Data') }}" class="pm-mono">{{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->created_at->format('d/m/Y H:i') }}</td><td data-label="{{ __('Metodo') }}">{{ $payment->method->label() }}</td><td data-label="{{ __('Importo') }}" class="pm-mono">€ {{ number_format((float) $payment->amount, 2, ',', '.') }}</td><td data-label="{{ __('Operatore') }}">{{ $payment->recordedBy?->name ?? '—' }}</td><td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $payment->status->value === 'paid' ? 'green' : 'gray' }}">{{ $payment->status->label() }}</span>@if($payment->reversal_reason)<span class="pm-td-sub">{{ $payment->reversal_reason }}</span>@endif</td><td data-label="{{ __('Azioni') }}">@if($payment->status->value === 'paid')<details class="pm-row-details"><summary>{{ __('Storna') }}</summary><form method="POST" action="{{ route('garage.payments.reverse', $payment) }}" class="pm-inline-reversal-form">@csrf<input name="reversal_reason" class="pm-input" placeholder="{{ __('Motivo dello storno') }}" required><button class="pm-btn pm-btn-danger pm-btn-sm" type="submit">{{ __('Conferma storno') }}</button></form></details>@else—@endif</td></tr>
                @empty<tr class="pm-responsive-empty"><td colspan="6">{{ __('Nessun pagamento registrato.') }}</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</x-app-layout>
