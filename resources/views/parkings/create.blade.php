<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Nuovo parcheggio') }}</h1>
            <div class="pm-page-subtitle">{{ __('Configurazione della nuova sede') }}</div>
        </div>
        <a href="{{ route('parkings.index') }}" class="pm-btn pm-btn-secondary">
            {{ __('Torna alla lista') }}
        </a>
    </x-slot>

    <div class="pm-card pm-animate">
        <form method="POST" action="{{ route('parkings.store') }}">
            @csrf

            <div class="pm-form-grid" style="margin-bottom: 24px;">
                <div class="pm-form-group">
                    <label class="pm-form-label" for="name">{{ __('Nome parcheggio') }}</label>
                    <input type="text" id="name" name="name" class="pm-form-control @error('name') pm-form-error @enderror" value="{{ old('name') }}" required>
                    @error('name') <div class="pm-form-error-msg">{{ $message }}</div> @enderror
                </div>

                <div class="pm-form-group">
                    <label class="pm-form-label" for="total_spots">{{ __('Capienza fisica totale (posti)') }}</label>
                    <input type="number" id="total_spots" name="total_spots" class="pm-form-control @error('total_spots') pm-form-error @enderror" value="{{ old('total_spots', 1) }}" min="1" required>
                    @error('total_spots') <div class="pm-form-error-msg">{{ $message }}</div> @enderror
                </div>

                <div class="pm-form-group">
                    <label class="pm-form-label" for="capacity_mode">{{ __('Gestione capacità') }}</label>
                    <select id="capacity_mode" name="capacity_mode" class="pm-form-control @error('capacity_mode') pm-form-error @enderror" required>
                        <option value="shared" {{ old('capacity_mode', 'shared') == 'shared' ? 'selected' : '' }}>{{ __('Condivisa (unico bacino)') }}</option>
                        <option value="per_product" {{ old('capacity_mode') == 'per_product' ? 'selected' : '' }}>{{ __('Per prodotto (aree separate)') }}</option>
                    </select>
                    @error('capacity_mode') <div class="pm-form-error-msg">{{ $message }}</div> @enderror
                </div>

                <div class="pm-form-group" style="grid-column: 1 / -1;">
                    <label class="pm-form-label pm-flex pm-items-center" for="parking_is_active" style="gap:8px; cursor:pointer;">
                        <input id="parking_is_active" type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} style="width:18px; height:18px;">
                        <span>{{ __('Parcheggio attivo') }}</span>
                    </label>
                </div>
            </div>

            <div style="text-align: right; border-top: 1px solid var(--pm-border); padding-top: 16px;">
                <button type="submit" class="pm-btn pm-btn-primary">
                    {{ __('Crea parcheggio') }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
