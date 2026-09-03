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
    <title>{{ config('app.name', 'Parking Manager Demo') }} - {{ __('Prenotazione pubblica') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pm-public" style="min-height:100vh;">

    <x-demo-banner />

    {{-- Topbar Branding --}}
    <div class="pm-public-topbar">
        <div class="pm-public-topbar-inner">
            <x-brand-logo variant="sidebar" />
            <div class="pm-public-topbar-divider"></div>
            <div class="pm-public-topbar-title">
                {{ __('Prenotazione parcheggio') }}
            </div>
            <div class="pm-public-topbar-language"><x-locale-switcher /></div>
        </div>
    </div>

    {{-- HERO HEADER --}}
    <div style="background: #1C1F2E; padding: 28px 24px 26px; position: relative; overflow: hidden;">
        <div style="max-width: 800px; margin: 0 auto; position: relative; z-index: 1;">
            <div style="font-size: 10px; font-weight: 500; letter-spacing: 1.8px; text-transform: uppercase; color: var(--pm-public-accent); margin-bottom: 7px;">
                {{ __('Parcheggio aeroportuale dimostrativo') }}
            </div>
            <div style="font-size: 22px; font-weight: 600; color: #fff; margin-bottom: 8px; line-height: 1.25;">
                {{ __('Prenota il tuo parcheggio') }}
            </div>
            <div style="font-size: 12px; color: rgba(255,255,255,0.45); display: flex; gap: 18px; flex-wrap: wrap;">
                <span style="display:flex; align-items:center; gap:5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ __('Dati interamente dimostrativi') }}
                </span>
                <span style="display:flex; align-items:center; gap:5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    {{ __('Sincronizzazioni API simulate') }}
                </span>
                <span style="display:flex; align-items:center; gap:5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    {{ __('Nessun pagamento reale') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Contenuto principale --}}
    <div style="max-width: 800px; margin: 32px auto; padding: 0 24px;">
        @yield('content')
    </div>

</body>
</html>
