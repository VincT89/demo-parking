@props(['compact' => false])

@php
    $languages = [
        'it' => ['code' => 'IT', 'label' => 'Italiano'],
        'en_GB' => ['code' => 'EN', 'label' => 'English'],
        'nl' => ['code' => 'NL', 'label' => 'Nederlands'],
    ];
    $availableLanguages = collect($languages)
        ->only(array_keys(config('app.supported_locales', [])));
    $currentLocale = app()->getLocale();
    $currentLanguage = $availableLanguages->get($currentLocale, $availableLanguages->first());
@endphp

<details
    class="pm-locale-switcher {{ $compact ? 'is-compact' : '' }}"
>
    <summary
        class="pm-locale-trigger"
        aria-label="{{ __('Lingua') }}: {{ $currentLanguage['label'] }} ({{ $currentLanguage['code'] }})"
        title="{{ $currentLanguage['label'] }}"
    >
        <x-locale-flag :locale="$currentLocale" />
        <span>{{ $currentLanguage['code'] }}</span>
        <svg class="pm-locale-chevron" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
            <path d="m4 6 4 4 4-4" />
        </svg>
    </summary>

    <div class="pm-locale-menu" aria-label="{{ __('Lingua') }}">
        @foreach ($availableLanguages as $locale => $language)
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <button
                    type="submit"
                    class="pm-locale-option {{ $currentLocale === $locale ? 'is-active' : '' }}"
                    aria-label="{{ $language['label'] }} ({{ $language['code'] }})"
                    aria-current="{{ $currentLocale === $locale ? 'true' : 'false' }}"
                    title="{{ $language['label'] }}"
                >
                    <x-locale-flag :locale="$locale" />
                    <span>{{ $language['code'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
</details>
