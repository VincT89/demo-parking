<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Simulatore Stripe') }}</h1>
            <div class="pm-page-subtitle">{{ __('Chiamata API e webhook simulati localmente') }}</div>
        </div>
        <a href="{{ route('garage.subscriptions.show', $payment->subscription) }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
    </x-slot>

    <div class="pm-narrow-page">
        <div class="pm-notice pm-notice-info"><strong>{{ __('Modalità demo') }}</strong><br>{{ __('Questa schermata non contatta Stripe e non effettua alcun addebito.') }}</div>
        <section class="pm-card">
            <div class="pm-card-header"><div><h2 class="pm-card-title">{{ __('Checkout abbonamento simulato') }}</h2><p class="pm-card-help">{{ $payment->subscription->reference }}</p></div><span class="pm-badge amber">{{ __('In attesa') }}</span></div>
            <dl class="pm-definition-grid">
                <div><dt>{{ __('Cliente') }}</dt><dd>{{ $payment->subscription->customer_name }}</dd></div>
                <div><dt>{{ __('Email') }}</dt><dd>{{ $payment->subscription->customer_email ?: '—' }}</dd></div>
                <div><dt>{{ __('Importo') }}</dt><dd>€ {{ number_format((float) $payment->amount, 2, ',', '.') }}</dd></div>
                <div><dt>{{ __('Periodo') }}</dt><dd>{{ $payment->billing_period_start->format('d/m/Y') }} – {{ $payment->billing_period_end->format('d/m/Y') }}</dd></div>
                <div class="pm-definition-total"><dt>{{ __('API simulata') }}</dt><dd class="pm-api-name">stripe.checkout.sessions.create</dd></div>
            </dl>
            <form method="POST" action="{{ route('garage.stripe.simulate', $payment) }}" class="pm-stripe-simulation-actions">
                @csrf
                <button type="submit" name="outcome" value="failure" class="pm-btn pm-btn-danger">{{ __('Simula pagamento non riuscito') }}</button>
                <button type="submit" name="outcome" value="success" class="pm-btn pm-btn-primary">{{ __('Simula pagamento riuscito') }}</button>
            </form>
        </section>
    </div>
</x-app-layout>
