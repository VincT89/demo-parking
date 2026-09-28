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
            @csrf @parkingContext

            <fieldset class="pm-management-options" x-data="{ separate: @js((bool) old('separate_management')) }">
                <legend>{{ __('Accessi al nuovo parcheggio') }}</legend>
                <label class="pm-checkbox-group"><input type="checkbox" name="separate_management" value="1" x-model="separate" @checked(old('separate_management'))><span>{{ __('Crea una gestione separata') }}</span></label>
                <p class="pm-form-note">{{ __('Se separata, solo tu avrai accesso inizialmente. Potrai aggiungere gli altri utenti dalla sezione Utenti. Altrimenti avranno accesso gli utenti della gestione corrente.') }}</p>
                <div class="pm-form-group" x-show="separate" x-cloak>
                    <label for="management-name" class="pm-label">{{ __('Nome della gestione') }}</label>
                    <input id="management-name" name="management_name" class="pm-input" value="{{ old('management_name') }}" maxlength="255" :required="separate">
                    @error('management_name')<div class="pm-form-error-msg" role="alert">{{ $message }}</div>@enderror
                </div>
            </fieldset>
            <div class="pm-settings-grid" style="margin-bottom: 24px;">
                <div class="pm-form-group">
                    <label class="pm-label" for="name">{{ __('Nome parcheggio') }}</label>
                    <input type="text" id="name" name="name" class="pm-input @error('name') pm-form-error @enderror" value="{{ old('name') }}" required>
                    @error('name') <div class="pm-form-error-msg" role="alert">{{ $message }}</div> @enderror
                </div>

                <div class="pm-form-group">
                    <label class="pm-label" for="total_spots">{{ __('Capienza fisica totale (posti)') }}</label>
                    <input type="number" id="total_spots" name="total_spots" class="pm-input @error('total_spots') pm-form-error @enderror" value="{{ old('total_spots', 1) }}" min="1" required>
                    @error('total_spots') <div class="pm-form-error-msg" role="alert">{{ $message }}</div> @enderror
                </div>

                <div class="pm-form-group">
                    <label class="pm-label" for="capacity_mode">{{ __('Gestione capacità') }}</label>
                    <select id="capacity_mode" name="capacity_mode" class="pm-select @error('capacity_mode') pm-form-error @enderror" required>
                        <option value="shared" {{ old('capacity_mode', 'shared') == 'shared' ? 'selected' : '' }}>{{ __('Condivisa (unico bacino)') }}</option>
                        <option value="per_product" {{ old('capacity_mode') == 'per_product' ? 'selected' : '' }}>{{ __('Per prodotto (aree separate)') }}</option>
                    </select>
                    @error('capacity_mode') <div class="pm-form-error-msg" role="alert">{{ $message }}</div> @enderror
                </div>

                <div class="pm-form-group" style="grid-column: 1 / -1;">
                    <label class="pm-label pm-flex pm-items-center" for="parking_is_active" style="gap:8px; cursor:pointer;">
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
