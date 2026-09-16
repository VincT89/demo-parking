@php
    $statusColor = match($subscription->status->value) { 'active' => 'green', 'suspended' => 'amber', 'cancelled' => 'red', default => 'gray' };
    $periodStart = $subscription->next_billing_on ?? $subscription->starts_on;
    $periodEnd = $periodStart->copy()->addMonthNoOverflow()->subDay();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Dettaglio abbonamento') }}</h1>
            <div class="pm-page-subtitle">{{ $subscription->reference }}</div>
        </div>
        <div class="pm-header-actions">
            <a href="{{ route('garage.subscriptions.edit', $subscription) }}" class="pm-btn pm-btn-primary">{{ __('Modifica') }}</a>
            <a href="{{ route('garage.subscriptions.index') }}" class="pm-btn pm-btn-secondary">{{ __('Elenco') }}</a>
        </div>
    </x-slot>

    <x-flash-message />

    <div class="pm-garage-detail">
        <div class="pm-garage-detail-grid">
            <section class="pm-card">
                <div class="pm-card-header">
                    <div><h2 class="pm-card-title">{{ $subscription->customer_name }}</h2><p class="pm-card-help pm-mono">{{ $subscription->license_plate }}</p></div>
                    <span class="pm-badge {{ $statusColor }}">{{ $subscription->status->label() }}</span>
                </div>
                <dl class="pm-definition-grid">
                    <div><dt>{{ __('Parcheggio') }}</dt><dd>{{ __($subscription->parking->name) }}</dd></div>
                    <div><dt>{{ __('Categoria') }}</dt><dd>{{ __($subscription->parkingProduct->name) }}</dd></div>
                    <div><dt>{{ __('Tariffa applicata') }}</dt><dd>{{ $subscription->rate ? __($subscription->rate->name) : __('Tariffa archiviata') }}</dd></div>
                    <div><dt>{{ __('Prezzo mensile') }}</dt><dd>€ {{ number_format((float) $subscription->price, 2, ',', '.') }}</dd></div>
                    <div><dt>{{ __('Data inizio') }}</dt><dd>{{ $subscription->starts_on->format('d/m/Y') }}</dd></div>
                    <div><dt>{{ __('Data fine') }}</dt><dd>{{ $subscription->ends_on?->format('d/m/Y') ?? __('Senza scadenza') }}</dd></div>
                    <div><dt>{{ __('Posti riservati') }}</dt><dd>{{ $subscription->reserved_spots }}</dd></div>
                    <div><dt>{{ __('Pagato fino al') }}</dt><dd>{{ $subscription->paid_through?->format('d/m/Y') ?? __('Nessun pagamento') }}</dd></div>
                    <div><dt>{{ __('Email') }}</dt><dd>{{ $subscription->customer_email ?: '—' }}</dd></div>
                    <div><dt>{{ __('Telefono') }}</dt><dd>{{ $subscription->customer_phone ?: '—' }}</dd></div>
                </dl>
                @if ($subscription->notes)<div class="pm-detail-notes"><strong>{{ __('Note') }}</strong><p>{{ $subscription->notes }}</p></div>@endif
            </section>

            <aside class="pm-gap">
                <section class="pm-card">
                    <h2 class="pm-card-title">{{ __('Registra pagamento') }}</h2>
                    <p class="pm-card-help">{{ __('Il pagamento resta nello storico con data e operatore.') }}</p>
                    <form method="POST" action="{{ route('garage.subscriptions.payments.store', $subscription) }}" class="pm-form pm-mt-20">
                        @csrf
                        <div class="pm-form-group"><label for="payment_method" class="pm-label">{{ __('Metodo') }}</label><select id="payment_method" name="method" class="pm-select">@foreach($paymentMethods as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></div>
                        <div class="pm-form-group"><label for="payment_amount" class="pm-label">{{ __('Importo') }}</label><div class="pm-input-suffix"><input id="payment_amount" name="amount" type="number" min="0.01" step="0.01" value="{{ $subscription->price }}" class="pm-input" required><span>€</span></div></div>
                        <div class="pm-form-group"><label for="payment_paid_at" class="pm-label">{{ __('Data pagamento') }}</label><input id="payment_paid_at" name="paid_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" class="pm-input" required></div>
                        <div class="pm-form-grid-2"><div class="pm-form-group"><label for="period_start" class="pm-label">{{ __('Periodo dal') }}</label><input id="period_start" name="billing_period_start" type="date" value="{{ $periodStart->format('Y-m-d') }}" class="pm-input" required></div><div class="pm-form-group"><label for="period_end" class="pm-label">{{ __('Periodo al') }}</label><input id="period_end" name="billing_period_end" type="date" value="{{ $periodEnd->format('Y-m-d') }}" class="pm-input" required></div></div>
                        <button type="submit" class="pm-btn pm-btn-primary pm-btn-block">{{ __('Segna come pagato') }}</button>
                    </form>
                </section>

                @if ($subscription->payment_mode === 'stripe')
                    <section class="pm-card">
                        <h2 class="pm-card-title">{{ __('Stripe') }}</h2>
                        <p class="pm-card-help">{{ config('demo.enabled') ? __('Apre il simulatore della chiamata Stripe senza usare denaro reale.') : __('Crea una sessione Stripe Checkout per l’abbonamento mensile.') }}</p>
                        <form method="POST" action="{{ route('garage.subscriptions.stripe.checkout', $subscription) }}" class="pm-mt-20">@csrf<button class="pm-btn pm-btn-secondary pm-btn-block" type="submit">{{ config('demo.enabled') ? __('Simula link Stripe') : __('Apri Stripe Checkout') }}</button></form>
                    </section>
                @endif
            </aside>
        </div>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header"><h2 class="pm-card-title">{{ __('Storico pagamenti') }}</h2></div>
            <div class="pm-table-wrapper"><table class="pm-table pm-responsive-table"><thead><tr><th>{{ __('Data') }}</th><th>{{ __('Periodo') }}</th><th>{{ __('Metodo') }}</th><th>{{ __('Importo') }}</th><th>{{ __('Operatore') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead><tbody>
                @forelse($subscription->payments as $payment)
                    <tr>
                        <td data-label="{{ __('Data') }}" class="pm-mono">{{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->created_at->format('d/m/Y H:i') }}</td>
                        <td data-label="{{ __('Periodo') }}" class="pm-mono">{{ $payment->billing_period_start?->format('d/m/Y') }} – {{ $payment->billing_period_end?->format('d/m/Y') }}</td>
                        <td data-label="{{ __('Metodo') }}">{{ $payment->method->label() }}@if($payment->provider === 'stripe')<span class="pm-td-sub">Stripe</span>@endif</td>
                        <td data-label="{{ __('Importo') }}" class="pm-mono">€ {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                        <td data-label="{{ __('Operatore') }}">{{ $payment->recordedBy?->name ?? ($payment->provider === 'stripe' ? __('Webhook Stripe') : '—') }}</td>
                        <td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $payment->status->value === 'paid' ? 'green' : ($payment->status->value === 'pending' ? 'amber' : 'gray') }}">{{ $payment->status->label() }}</span>@if($payment->reversal_reason)<span class="pm-td-sub">{{ $payment->reversal_reason }}</span>@endif</td>
                        <td data-label="{{ __('Azioni') }}">@if($payment->status->value === 'paid' && ($payment->provider !== 'stripe' || config('demo.enabled')))<details class="pm-row-details"><summary>{{ __('Storna') }}</summary><form method="POST" action="{{ route('garage.payments.reverse', $payment) }}" class="pm-inline-reversal-form">@csrf<input name="reversal_reason" class="pm-input" placeholder="{{ __('Motivo dello storno') }}" required><button class="pm-btn pm-btn-danger pm-btn-sm" type="submit">{{ __('Conferma storno') }}</button></form></details>@else—@endif</td>
                    </tr>
                @empty<tr class="pm-responsive-empty"><td colspan="7">{{ __('Nessun pagamento registrato.') }}</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header"><h2 class="pm-card-title">{{ __('Ultime soste collegate') }}</h2><a href="{{ route('garage.stays.create') }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Registra ingresso') }}</a></div>
            <div class="pm-table-wrapper"><table class="pm-table pm-responsive-table"><thead><tr><th>{{ __('Riferimento') }}</th><th>{{ __('Ingresso') }}</th><th>{{ __('Uscita') }}</th><th>{{ __('Stato') }}</th><th>{{ __('Azioni') }}</th></tr></thead><tbody>
                @forelse($subscription->stays as $stay)<tr><td data-label="{{ __('Riferimento') }}" class="pm-mono">{{ $stay->reference }}</td><td data-label="{{ __('Ingresso') }}" class="pm-mono">{{ $stay->starts_at->format('d/m/Y H:i') }}</td><td data-label="{{ __('Uscita') }}" class="pm-mono">{{ $stay->ended_at?->format('d/m/Y H:i') ?? '—' }}</td><td data-label="{{ __('Stato') }}">{{ $stay->status->label() }}</td><td data-label="{{ __('Azioni') }}"><a href="{{ route('garage.stays.show', $stay) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Apri') }}</a></td></tr>@empty<tr class="pm-responsive-empty"><td colspan="5">{{ __('Nessuna sosta collegata.') }}</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
    @if($subscription->customer_id)
        <div class="pm-mt-20"><a class="pm-btn pm-btn-secondary" href="{{ route('customers.show', $subscription->customer_id) }}">{{ __('Scheda cliente') }}</a></div>
    @endif
</x-app-layout>
