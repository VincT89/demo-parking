<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Modifica abbonamento') }}</h1>
            <div class="pm-page-subtitle">{{ $subscription->reference }}</div>
        </div>
        <a href="{{ route('garage.subscriptions.show', $subscription) }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-narrow-page">
        <div class="pm-notice pm-notice-info">{{ __('La categoria e il prezzo originario non cambiano: così capacità e storico economico restano coerenti.') }}</div>
        <div class="pm-card">
            <form method="POST" action="{{ route('garage.subscriptions.update', $subscription) }}" class="pm-form">
                @csrf
                @method('PUT')
                <x-customer-picker :record="$subscription" />
                <div class="pm-form-grid-2">
                    <div class="pm-form-group"><label class="pm-label">{{ __('Parcheggio') }}</label><input value="{{ __($subscription->parking->name) }}" class="pm-input" disabled></div>
                    <div class="pm-form-group"><label class="pm-label">{{ __('Categoria parcheggio') }}</label><input value="{{ __($subscription->parkingProduct->name) }}" class="pm-input" disabled></div>
                    <div class="pm-form-group"><label for="customer_name" class="pm-label pm-label-required">{{ __('Cliente') }}</label><input id="customer_name" name="customer_name" value="{{ old('customer_name', $subscription->customer_name) }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="license_plate" class="pm-label pm-label-required">{{ __('Targa') }}</label><input id="license_plate" name="license_plate" value="{{ old('license_plate', $subscription->license_plate) }}" class="pm-input pm-uppercase" required></div>
                    <div class="pm-form-group"><label for="customer_email" class="pm-label">{{ __('Email') }}</label><input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', $subscription->customer_email) }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="customer_phone" class="pm-label">{{ __('Telefono') }}</label><input id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $subscription->customer_phone) }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="starts_on" class="pm-label pm-label-required">{{ __('Data inizio') }}</label><input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', $subscription->starts_on->format('Y-m-d')) }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="ends_on" class="pm-label">{{ __('Data fine') }}</label><input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $subscription->ends_on?->format('Y-m-d')) }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="reserved_spots" class="pm-label pm-label-required">{{ __('Posti riservati') }}</label><input id="reserved_spots" name="reserved_spots" type="number" min="1" max="50" value="{{ old('reserved_spots', $subscription->reserved_spots) }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="status" class="pm-label pm-label-required">{{ __('Stato') }}</label><select id="status" name="status" class="pm-select" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $subscription->status->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                    <div class="pm-form-group pm-form-span-2"><label for="payment_mode" class="pm-label pm-label-required">{{ __('Gestione pagamento') }}</label><select id="payment_mode" name="payment_mode" class="pm-select" required><option value="manual" @selected(old('payment_mode', $subscription->payment_mode) === 'manual')>{{ __('Registrazione manuale') }}</option><option value="stripe" @selected(old('payment_mode', $subscription->payment_mode) === 'stripe')>{{ __('Stripe ricorrente / link di pagamento') }}</option></select></div>
                    <div class="pm-form-group pm-form-span-2"><label for="notes" class="pm-label">{{ __('Note') }}</label><textarea id="notes" name="notes" class="pm-textarea">{{ old('notes', $subscription->notes) }}</textarea></div>
                </div>
                <div class="pm-form-actions"><a href="{{ route('garage.subscriptions.show', $subscription) }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a><button type="submit" class="pm-btn pm-btn-primary">{{ __('Salva modifiche') }}</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
