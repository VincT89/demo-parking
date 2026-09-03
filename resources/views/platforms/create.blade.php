<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Nuova piattaforma') }}</h1>
            <div class="pm-page-subtitle">{{ __('Aggiungi un canale di vendita') }}</div>
        </div>
        <a href="{{ route('platforms.index') }}" class="pm-btn pm-btn-secondary">
            {{ __('Torna alla lista') }}
        </a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-animate" style="max-width:720px">
        <form method="POST" action="{{ route('platforms.store') }}" class="pm-form">
            @csrf

            <div class="pm-form-grid-2">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required" for="platform_name">{{ __('Nome') }}</label>
                    <input id="platform_name" type="text" name="name" value="{{ old('name') }}"
                           required class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required" for="platform_slug">{{ __('Slug') }}</label>
                    <input id="platform_slug" type="text" name="slug" value="{{ old('slug') }}"
                           required class="pm-input" placeholder="es. parking-my-car" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label" for="platform_website">{{ __('Sito web') }}</label>
                    <input id="platform_website" type="url" name="website" value="{{ old('website') }}"
                           class="pm-input" placeholder="https://..." />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label" for="platform_contact_email">{{ __('Email di contatto') }}</label>
                    <input id="platform_contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}"
                           class="pm-input" />
                </div>
            </div>



            <div class="pm-checkbox-group">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       {{ old('is_active', true) ? 'checked' : '' }}
                       class="pm-checkbox" />
                <label for="is_active" class="pm-checkbox-label">{{ __('Piattaforma attiva') }}</label>
            </div>

            <div class="pm-form-actions">
                <button type="submit" class="pm-btn pm-btn-primary">{{ __('Crea piattaforma') }}</button>
                <a href="{{ route('platforms.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
            </div>

        </form>
    </div>

</x-app-layout>
