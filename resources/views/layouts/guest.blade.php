@props(['title' => null])

<!DOCTYPE html>
<html
    lang="{{ config('app.supported_locales.'.app()->getLocale().'.html', str_replace('_', '-', app()->getLocale())) }}"
    data-show-password="{{ __('Mostra password') }}"
    data-hide-password="{{ __('Nascondi password') }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ParkManager') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pm-auth-body">
    <main class="pm-auth-shell">
        <section class="pm-auth-panel">
            <h1 class="pm-sr-only">{{ $title ?? __('Accedi') }}</h1>

            <div class="pm-auth-language">
                <x-locale-switcher />
            </div>

            <div class="pm-auth-logo">
                <x-brand-logo />
            </div>

            {{ $slot }}
        </section>
    </main>
</body>
</html>
