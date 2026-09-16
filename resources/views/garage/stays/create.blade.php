<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Registra ingresso') }}</h1>
            <div class="pm-page-subtitle">{{ __('Sosta giornaliera o accesso di un abbonato') }}</div>
        </div>
        <a href="{{ route('garage.stays.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-narrow-page">
        <div class="pm-notice pm-notice-info">{{ __('L’uscita prevista è necessaria per proteggere la disponibilità delle prenotazioni future.') }}</div>
        <div class="pm-card">
            <form method="POST" action="{{ route('garage.stays.store') }}" class="pm-form" id="stay-form">
                @csrf
                <div data-walk-in><x-customer-picker /></div>
                <div class="pm-form-grid-2">
                    <div class="pm-form-group pm-form-span-2"><label for="stay_mode" class="pm-label pm-label-required">{{ __('Tipo ingresso') }}</label><select id="stay_mode" class="pm-select"><option value="walk_in">{{ __('Cliente giornaliero') }}</option><option value="subscription">{{ __('Cliente abbonato') }}</option></select></div>
                    <div class="pm-form-group"><label for="stay_parking" class="pm-label pm-label-required">{{ __('Parcheggio') }}</label><select id="stay_parking" name="parking_id" class="pm-select" required>@foreach($parkings as $parking)<option value="{{ $parking->id }}" @selected(old('parking_id') == $parking->id)>{{ __($parking->name) }}</option>@endforeach</select></div>
                    <div class="pm-form-group" data-walk-in><label for="stay_product" class="pm-label pm-label-required">{{ __('Categoria parcheggio') }}</label><select id="stay_product" name="parking_product_id" class="pm-select">@foreach($parkings as $parking) @foreach($parking->products as $product)<option value="{{ $product->id }}" data-parking="{{ $parking->id }}" @selected(old('parking_product_id') == $product->id)>{{ __($product->name) }}</option>@endforeach @endforeach</select></div>
                    <div class="pm-form-group pm-form-span-2" data-walk-in>
                        <label for="stay_rate" class="pm-label pm-label-required">{{ __('Tariffa giornaliera') }}</label>
                        <select id="stay_rate" name="garage_rate_id" class="pm-select" required>
                            <option value="" data-empty-option>{{ __('Nessuna tariffa configurata.') }}</option>
                            @foreach($parkings as $parking)
                                @foreach($parking->garageRates as $rate)
                                    <option value="{{ $rate->id }}" data-parking="{{ $parking->id }}" data-product="{{ $rate->parking_product_id }}" @selected(old('garage_rate_id') == $rate->id)>{{ __($rate->name) }} · € {{ number_format((float) $rate->price, 2, ',', '.') }} / {{ $rate->billing_unit->label() }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <div class="pm-rate-empty-help" data-rate-empty hidden>
                            <span class="pm-field-help">{{ __('Aggiungi almeno una tariffa mensile e una giornaliera per usare il modulo garage.') }}</span>
                            @can('manage-parkings')
                                <a
                                    href="{{ route('garage.rates.index', ['parking_id' => old('parking_id', $parkings->first()?->id)]) }}"
                                    class="pm-rate-config-link"
                                    data-rate-settings
                                    data-base-url="{{ route('garage.rates.index') }}"
                                >{{ __('Configura tariffe') }}</a>
                            @endcan
                        </div>
                    </div>
                    <div class="pm-form-group pm-form-span-2" data-subscription hidden><label for="stay_subscription" class="pm-label pm-label-required">{{ __('Abbonamento') }}</label><select id="stay_subscription" name="parking_subscription_id" class="pm-select" disabled>@foreach($subscriptions as $subscription)<option value="{{ $subscription->id }}" data-parking="{{ $subscription->parking_id }}" @selected(old('parking_subscription_id') == $subscription->id)>{{ $subscription->customer_name }} · {{ $subscription->license_plate }} · {{ $subscription->reference }}</option>@endforeach</select></div>
                    <div class="pm-form-group"><label for="stay_customer" class="pm-label">{{ __('Cliente') }}</label><input id="stay_customer" name="customer_name" value="{{ old('customer_name') }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="stay_plate" class="pm-label" data-plate-label>{{ __('Targa') }}</label><input id="stay_plate" name="license_plate" value="{{ old('license_plate') }}" class="pm-input pm-uppercase"></div>
                    <div class="pm-form-group"><label for="stay_email" class="pm-label">{{ __('Email') }}</label><input id="stay_email" name="customer_email" type="email" value="{{ old('customer_email') }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="stay_phone" class="pm-label">{{ __('Telefono') }}</label><input id="stay_phone" name="customer_phone" value="{{ old('customer_phone') }}" class="pm-input"></div>
                    <div class="pm-form-group"><label for="starts_at" class="pm-label pm-label-required">{{ __('Ingresso') }}</label><input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="expected_ends_at" class="pm-label pm-label-required">{{ __('Uscita prevista') }}</label><input id="expected_ends_at" name="expected_ends_at" type="datetime-local" value="{{ old('expected_ends_at', now()->addDay()->format('Y-m-d\TH:i')) }}" class="pm-input" required></div>
                    <div class="pm-form-group pm-form-span-2"><label for="stay_notes" class="pm-label">{{ __('Note') }}</label><textarea id="stay_notes" name="notes" class="pm-textarea">{{ old('notes') }}</textarea></div>
                </div>
                <div class="pm-form-actions"><a href="{{ route('garage.stays.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a><button class="pm-btn pm-btn-primary" type="submit">{{ __('Conferma ingresso') }}</button></div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const mode = document.getElementById('stay_mode');
            const parking = document.getElementById('stay_parking');
            const product = document.getElementById('stay_product');
            const rate = document.getElementById('stay_rate');
            const subscription = document.getElementById('stay_subscription');
            const plate = document.getElementById('stay_plate');
            const rateEmpty = document.querySelector('[data-rate-empty]');
            const rateSettings = document.querySelector('[data-rate-settings]');
            const filter = (select, predicate) => {
                const current = select.value;
                let first = '';
                [...select.options].forEach(option => { const visible = predicate(option); option.hidden = !visible; option.disabled = !visible; if (visible && !first) first = option.value; });
                if (!select.options[select.selectedIndex] || select.options[select.selectedIndex].disabled) select.value = first;
                if (current && [...select.options].some(option => option.value === current && !option.disabled)) select.value = current;
            };
            const syncRates = () => {
                const current = rate.value;
                const emptyOption = rate.querySelector('[data-empty-option]');
                const matching = [...rate.options].filter(option => !option.hasAttribute('data-empty-option') && option.dataset.parking === parking.value && option.dataset.product === product.value);
                [...rate.options].forEach(option => {
                    if (option.hasAttribute('data-empty-option')) return;
                    const visible = matching.includes(option);
                    option.hidden = !visible;
                    option.disabled = !visible;
                });
                const hasRates = matching.length > 0;
                emptyOption.hidden = hasRates;
                emptyOption.disabled = hasRates;
                rate.value = current && matching.some(option => option.value === current) ? current : (matching[0]?.value ?? '');
                rateEmpty.hidden = hasRates;
                if (rateSettings) {
                    const settingsUrl = new URL(rateSettings.dataset.baseUrl, window.location.origin);
                    settingsUrl.searchParams.set('parking_id', parking.value);
                    rateSettings.href = settingsUrl.toString();
                }
            };
            const syncParking = () => { filter(product, option => option.dataset.parking === parking.value); filter(subscription, option => option.dataset.parking === parking.value); syncRates(); };
            const syncMode = () => {
                const isSubscription = mode.value === 'subscription';
                document.querySelectorAll('[data-walk-in]').forEach(element => element.hidden = isSubscription);
                document.querySelectorAll('[data-subscription]').forEach(element => element.hidden = !isSubscription);
                product.disabled = isSubscription; rate.disabled = isSubscription; subscription.disabled = !isSubscription;
                document.querySelector('[data-customer-id]').disabled = isSubscription;
                plate.required = !isSubscription;
            };
            parking.addEventListener('change', syncParking); product.addEventListener('change', syncRates); mode.addEventListener('change', syncMode);
            @if(old('parking_subscription_id')) mode.value = 'subscription'; @endif
            syncParking(); syncMode();
        })();
    </script>
</x-app-layout>
