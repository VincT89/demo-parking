@extends('layouts.public')

@section('page_title', __('Pagamento abbonamento'))
@section('topbar_title', __('Pagamento abbonamento'))
@section('hero_kicker', __('Area pagamento sicura'))
@section('hero_title', __('Esito pagamento abbonamento'))

@section('content')
    @php $paid = $subscription->latestPayment?->status?->value === 'paid'; @endphp
    <div class="pm-card pm-public-result-card">
        <h2 class="pm-card-title">{{ $cancelled ? __('Pagamento annullato') : ($paid ? __('Pagamento ricevuto') : __('Pagamento in elaborazione')) }}</h2>
        <p class="pm-public-result-copy">
            {{ $cancelled
                ? __('Non è stato registrato alcun nuovo pagamento. Puoi usare nuovamente il link ricevuto.')
                : ($paid
                    ? __('Il pagamento risulta confermato. Il gestore del parcheggio vedrà automaticamente l’aggiornamento.')
                    : __('Stripe sta completando la conferma. Se hai già pagato, non ripetere l’operazione e attendi qualche minuto.')) }}
        </p>
        <dl class="pm-definition-grid">
            <div><dt>{{ __('Riferimento') }}</dt><dd>{{ $subscription->reference }}</dd></div>
            <div><dt>{{ __('Stato') }}</dt><dd>{{ $cancelled ? __('Annullato') : ($paid ? __('Pagato') : __('In attesa')) }}</dd></div>
        </dl>
    </div>
@endsection
