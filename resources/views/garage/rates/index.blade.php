<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Tariffe garage') }}</h1>
            <div class="pm-page-subtitle">{{ __('Prezzi configurabili per abbonamenti e soste giornaliere') }}</div>
        </div>
        <a href="{{ route('garage.index', ['parking_id' => $parking->id]) }}" class="pm-btn pm-btn-secondary">{{ __('Torna al garage') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-settings-page">
        <div class="pm-card pm-settings-selector">
            <form method="GET" action="{{ route('garage.rates.index') }}" class="pm-inline-filter">
                <div class="pm-form-group">
                    <label for="rates_parking_id" class="pm-label">{{ __('Parcheggio') }}</label>
                    <select id="rates_parking_id" name="parking_id" class="pm-select" onchange="this.form.submit()">
                        @foreach ($parkings as $item)
                            <option value="{{ $item->id }}" @selected($item->is($parking))>{{ __($item->name) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header pm-settings-card-header">
                <div>
                    <h2 class="pm-card-title">{{ __('Nuova tariffa') }}</h2>
                    <p class="pm-card-help">{{ __('I prezzi inseriti valgono per i nuovi contratti o ingressi; lo storico conserva il prezzo applicato.') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('garage.rates.store') }}" class="pm-form">
                @csrf
                <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                @include('garage.rates.partials.fields', ['rate' => null])
                <div class="pm-form-actions"><button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva tariffa') }}</button></div>
            </form>
        </section>

        <section class="pm-rate-list" aria-label="{{ __('Tariffe configurate') }}">
            @forelse ($rates as $rate)
                <article class="pm-card">
                    <form method="POST" action="{{ route('garage.rates.update', $rate) }}" class="pm-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                        <div class="pm-card-header pm-settings-card-header">
                            <div>
                                <h2 class="pm-card-title">{{ __($rate->name) }}</h2>
                                <p class="pm-card-help">{{ $rate->kind->label() }} · {{ __($rate->parkingProduct->name) }}</p>
                            </div>
                            <span class="pm-badge {{ $rate->is_active ? 'green' : 'gray' }}">{{ $rate->is_active ? __('Attiva') : __('Disattivata') }}</span>
                        </div>
                        @include('garage.rates.partials.fields', ['rate' => $rate])
                        <div class="pm-form-actions"><button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva modifiche') }}</button></div>
                    </form>
                    @if ($rate->is_active)
                        <form method="POST" action="{{ route('garage.rates.destroy', $rate) }}" onsubmit="return confirm(@js(__('Disattivare questa tariffa?')))" class="pm-rate-disable-form">
                            @csrf
                            @method('DELETE')
                            <button class="pm-btn pm-btn-danger" type="submit">{{ __('Disattiva') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="pm-card pm-empty-state">
                    <strong>{{ __('Nessuna tariffa configurata.') }}</strong>
                    <p>{{ __('Aggiungi almeno una tariffa mensile e una giornaliera per usare il modulo garage.') }}</p>
                </div>
            @endforelse
        </section>
    </div>
</x-app-layout>
