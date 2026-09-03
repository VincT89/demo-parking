<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Nuovo blocco disponibilità') }}</h1>
            <div class="pm-page-subtitle">{{ __('Chiusura, manutenzione o blocco manuale') }}</div>
        </div>
        <a href="{{ route('availability-blocks.index') }}" class="pm-btn pm-btn-secondary">
            {{ __('Torna alla lista') }}
        </a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-animate" style="max-width:720px">
        <form method="POST" action="{{ route('availability-blocks.store') }}" class="pm-form">
            @csrf

            <div class="pm-form-grid-2">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Parcheggio') }}</label>
                    <select name="parking_id" required class="pm-select">
                        <option value="">{{ __('Seleziona parcheggio...') }}</option>
                        @foreach ($parkings as $parking)
                            <option value="{{ $parking->id }}"
                                {{ old('parking_id') == $parking->id ? 'selected' : '' }}>
                                {{ __($parking->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pm-form-group">
                    <label class="pm-label">
                        {{ __('Canale specifico') }}
                        <span class="pm-text-muted" style="font-weight:400;text-transform:none;letter-spacing:0">
                            {{ __('(vuoto = tutti)') }}
                        </span>
                    </label>
                    <select name="parking_listing_id" class="pm-select">
                        <option value="">{{ __('Tutti i canali') }}</option>
                        @foreach ($listings as $listing)
                            <option value="{{ $listing->id }}"
                                {{ old('parking_listing_id') == $listing->id ? 'selected' : '' }}>
                                {{ __($listing->platform->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="pm-form-grid-2">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Tipo blocco') }}</label>
                    <select name="type" required class="pm-select">
                        <option value="">{{ __('Seleziona tipo...') }}</option>
                        @foreach ($blockTypes as $type)
                            <option value="{{ $type->value }}"
                                {{ old('type') == $type->value ? 'selected' : '' }}>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Posti bloccati') }}</label>
                    <input type="number" name="spots" min="1"
                           value="{{ old('spots', 1) }}" required class="pm-input" />
                </div>
            </div>

            <div class="pm-form-grid-2">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Dal') }}</label>
                    <input type="datetime-local" name="starts_at"
                           value="{{ old('starts_at') }}" required class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Al') }}</label>
                    <input type="datetime-local" name="ends_at"
                           value="{{ old('ends_at') }}" required class="pm-input" />
                </div>
            </div>

            <div class="pm-form-group">
                <label class="pm-label">{{ __('Motivo') }}</label>
                <textarea name="reason" class="pm-textarea"
                          placeholder="{{ __('Descrivi il motivo del blocco...') }}">{{ old('reason') }}</textarea>
            </div>

            <div class="pm-form-actions">
                <button type="submit" class="pm-btn pm-btn-primary">{{ __('Crea blocco') }}</button>
                <a href="{{ route('availability-blocks.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
            </div>

        </form>
    </div>

</x-app-layout>
