<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Nuovo abbonamento') }}</h1>
            <div class="pm-page-subtitle">{{ __('Il posto viene riservato nella capacità, senza assegnazione fisica') }}</div>
        </div>
        <a href="{{ route('garage.subscriptions.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-narrow-page">
        @if ($parkings->flatMap->garageRates->isEmpty())
            <div class="pm-notice pm-notice-danger">{{ __('Prima di creare un abbonamento configura almeno una tariffa mensile attiva.') }}</div>
        @endif
        <div class="pm-card">
            <form method="POST" action="{{ route('garage.subscriptions.store') }}" class="pm-form" id="subscription-form">
                @csrf
                <div class="pm-form-grid-2">
                    <div class="pm-form-group">
                        <label for="subscription_parking" class="pm-label pm-label-required">{{ __('Parcheggio') }}</label>
                        <select id="subscription_parking" name="parking_id" class="pm-select" required>
                            @foreach ($parkings as $parking)<option value="{{ $parking->id }}" @selected(old('parking_id') == $parking->id)>{{ __($parking->name) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="pm-form-group">
                        <label for="subscription_product" class="pm-label pm-label-required">{{ __('Categoria parcheggio') }}</label>
                        <select id="subscription_product" name="parking_product_id" class="pm-select" required>
                            @foreach ($parkings as $parking) @foreach ($parking->products as $product)<option value="{{ $product->id }}" data-parking="{{ $parking->id }}" @selected(old('parking_product_id') == $product->id)>{{ __($product->name) }}</option>@endforeach @endforeach
                        </select>
                    </div>
                    <div class="pm-form-group pm-form-span-2">
                        <label for="subscription_rate" class="pm-label pm-label-required">{{ __('Tariffa mensile') }}</label>
                        <select id="subscription_rate" name="garage_rate_id" class="pm-select" required>
                            @foreach ($parkings as $parking) @foreach ($parking->garageRates as $rate)<option value="{{ $rate->id }}" data-parking="{{ $parking->id }}" data-product="{{ $rate->parking_product_id }}" @selected(old('garage_rate_id') == $rate->id)>{{ __($rate->name) }} · € {{ number_format((float) $rate->price, 2, ',', '.') }}</option>@endforeach @endforeach
                        </select>
                    </div>
                    <div class="pm-form-group"><label for="customer_name" class="pm-label pm-label-required">{{ __('Cliente') }}</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="license_plate" class="pm-label pm-label-required">{{ __('Targa') }}</label><input id="license_plate" name="license_plate" value="{{ old('license_plate') }}" class="pm-input pm-uppercase" maxlength="32" required></div>
                    <div class="pm-form-group"><label for="customer_email" class="pm-label">{{ __('Email') }}</label><input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="customer_phone" class="pm-label">{{ __('Telefono') }}</label><input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="starts_on" class="pm-label pm-label-required">{{ __('Data inizio') }}</label><input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', now()->toDateString()) }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="ends_on" class="pm-label">{{ __('Data fine') }}</label><input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on') }}" class="pm-input"><small class="pm-field-help">{{ __('Lascia vuoto per un abbonamento senza scadenza prestabilita.') }}</small></div>
                    <div class="pm-form-group"><label for="reserved_spots" class="pm-label pm-label-required">{{ __('Posti riservati') }}</label><input id="reserved_spots" name="reserved_spots" type="number" min="1" max="50" value="{{ old('reserved_spots', 1) }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="payment_mode" class="pm-label pm-label-required">{{ __('Gestione pagamento') }}</label><select id="payment_mode" name="payment_mode" class="pm-select" required><option value="manual" @selected(old('payment_mode', 'manual') === 'manual')>{{ __('Registrazione manuale') }}</option><option value="stripe" @selected(old('payment_mode') === 'stripe')>{{ __('Stripe ricorrente / link di pagamento') }}</option></select></div>
                    <div class="pm-form-group pm-form-span-2"><label for="notes" class="pm-label">{{ __('Note') }}</label><textarea id="notes" name="notes" class="pm-textarea">{{ old('notes') }}</textarea></div>
                </div>
                <div class="pm-form-actions"><a href="{{ route('garage.subscriptions.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a><button class="pm-btn pm-btn-primary" type="submit" @disabled($parkings->flatMap->garageRates->isEmpty())>{{ __('Crea abbonamento') }}</button></div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const parking = document.getElementById('subscription_parking');
            const product = document.getElementById('subscription_product');
            const rate = document.getElementById('subscription_rate');
            const filter = (select, predicate) => {
                const current = select.value;
                let first = '';
                [...select.options].forEach(option => {
                    const visible = predicate(option);
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible && !first) first = option.value;
                });
                if (!select.options[select.selectedIndex] || select.options[select.selectedIndex].disabled) select.value = first;
                if (current && [...select.options].some(option => option.value === current && !option.disabled)) select.value = current;
            };
            const syncRates = () => filter(rate, option => option.dataset.parking === parking.value && option.dataset.product === product.value);
            const sync = () => { filter(product, option => option.dataset.parking === parking.value); syncRates(); };
            parking.addEventListener('change', sync);
            product.addEventListener('change', syncRates);
            sync();
        })();
    </script>
</x-app-layout>
