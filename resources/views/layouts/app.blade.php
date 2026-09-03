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

<body>

    <div class="pm-layout" id="pm-layout">
        <div class="pm-sidebar-overlay" id="pm-sidebar-overlay" onclick="toggleSidebarMobile()"></div>

        {{-- SIDEBAR --}}
        <aside class="pm-sidebar" id="pm-sidebar">

            <div class="pm-sidebar-header">
                <a href="{{ route('dashboard') }}" class="pm-sidebar-logo">
                    <x-brand-logo variant="sidebar" />
                </a>
                <button class="pm-sidebar-toggle" onclick="toggleSidebar()" title="{{ __('Comprimi sidebar') }}" aria-label="{{ __('Comprimi sidebar') }}">
                    <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                        <polyline points="9,3 4,7.5 9,12" />
                    </svg>
                </button>
            </div>

            <nav class="pm-sidebar-nav">

                <div class="pm-sidebar-section">{{ __('Principale') }}</div>

                <a href="{{ route('dashboard') }}"
                    class="pm-sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="{{ __('Dashboard') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="1" y="1" width="5.5" height="5.5" rx="1" />
                            <rect x="8.5" y="1" width="5.5" height="5.5" rx="1" />
                            <rect x="1" y="8.5" width="5.5" height="5.5" rx="1" />
                            <rect x="8.5" y="8.5" width="5.5" height="5.5" rx="1" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Dashboard') }}</span>
                </a>

                <a href="{{ route('reservations.index') }}"
                    class="pm-sidebar-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}"
                    title="{{ __('Prenotazioni') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="1" y="2" width="13" height="11" rx="1.5" />
                            <line x1="1" y1="6" x2="14" y2="6" />
                            <line x1="5" y1="2" x2="5" y2="6" />
                            <line x1="10" y1="2" x2="10" y2="6" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Prenotazioni') }}</span>
                </a>

                <a href="{{ route('public.booking.form') }}"
                    class="pm-sidebar-link"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="{{ __('Pagina prenotazioni') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M8.5 1.5h5v5" />
                            <path d="M13.5 1.5 7 8" />
                            <path d="M6 3H2.5a1 1 0 0 0-1 1v8.5a1 1 0 0 0 1 1H11a1 1 0 0 0 1-1V9" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Pagina prenotazioni') }}</span>
                </a>

                <a href="{{ route('calendar') }}"
                    class="pm-sidebar-link {{ request()->routeIs('calendar*') ? 'active' : '' }}" title="{{ __('Calendario') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="1" y="2" width="13" height="11" rx="1.5" />
                            <line x1="1" y1="6" x2="14" y2="6" />
                            <line x1="5" y1="2" x2="5" y2="6" />
                            <line x1="10" y1="2" x2="10" y2="6" />
                            <line x1="4" y1="9" x2="7" y2="9" />
                            <line x1="4" y1="11.5" x2="9" y2="11.5" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Calendario') }}</span>
                </a>

                <div class="pm-sidebar-section">{{ __('Operativo') }}</div>

                <a href="{{ route('availability-blocks.index') }}"
                    class="pm-sidebar-link {{ request()->routeIs('availability-blocks.*') ? 'active' : '' }}"
                    title="{{ __('Blocchi') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="7.5" cy="7.5" r="6" />
                            <line x1="4.5" y1="4.5" x2="10.5" y2="10.5" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Blocchi') }}</span>
                </a>

                <a href="{{ route('alerts') }}"
                    class="pm-sidebar-link {{ request()->routeIs('alerts*') ? 'active' : '' }}" title="{{ __('Avvisi') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7.5 1L1 13h13L7.5 1z" />
                            <line x1="7.5" y1="6" x2="7.5" y2="9" />
                            <circle cx="7.5" cy="11" r="0.5" fill="currentColor" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label"
                        style="display:flex;align-items:center;justify-content:space-between;flex:1">
                        {{ __('Avvisi') }}
                        @if (!empty($alertCount) && $alertCount > 0)
                            <span style="background:var(--pm-red);color:white;font-size:10px;font-family:var(--pm-mono);font-weight:500;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;line-height:16px;">{{ $alertCount }}</span>
                        @endif
                    </span>
                </a>

                <div class="pm-sidebar-section">{{ __('Report') }}</div>

                <a href="{{ route('analytics') }}"
                    class="pm-sidebar-link {{ request()->routeIs('analytics*') ? 'active' : '' }}" title="{{ __('Analisi') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <polyline points="1,11 5,6 8,9 11,4 14,7" />
                            <line x1="1" y1="13" x2="14" y2="13" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Analisi') }}</span>
                </a>

                <a href="{{ route('invoices.index') }}"
                    class="pm-sidebar-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}" title="{{ __('Fatture') }}">
                    <span class="pm-sidebar-link-icon">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 1.5h7l2 2v10H3z" />
                            <path d="M10 1.5v2h2M5 7h5M5 9.5h5M5 12h3" />
                        </svg>
                    </span>
                    <span class="pm-sidebar-link-label">{{ __('Fatture') }}</span>
                </a>

                @if (auth()->user()->isAdmin())
                    <div class="pm-sidebar-section">{{ __('Amministrazione') }}</div>
                    <a href="{{ route('platforms.index') }}"
                        class="pm-sidebar-link {{ request()->routeIs('platforms.*') ? 'active' : '' }}"
                        title="{{ __('Piattaforme') }}">
                        <span class="pm-sidebar-link-icon">
                            <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="7.5" cy="7.5" r="2" />
                                <circle cx="7.5" cy="7.5" r="6" />
                                <line x1="7.5" y1="1.5" x2="7.5" y2="5.5" />
                                <line x1="7.5" y1="9.5" x2="7.5" y2="13.5" />
                                <line x1="1.5" y1="7.5" x2="5.5" y2="7.5" />
                                <line x1="9.5" y1="7.5" x2="13.5" y2="7.5" />
                            </svg>
                        </span>
                        <span class="pm-sidebar-link-label">{{ __('Piattaforme') }}</span>
                    </a>
                @endif

                @can('manage-parkings')
                    <a href="{{ route('parkings.index') }}"
                        class="pm-sidebar-link {{ request()->routeIs('parkings.*') ? 'active' : '' }}"
                        title="{{ __('Parcheggi') }}">
                        <span class="pm-sidebar-link-icon">
                            <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="7.5" cy="7.5" r="2.5" />
                                <path d="M7.5 1v1.5m0 10V14m5.5-6.5h-1.5m-10 0H1m11-4.5l-1 1m-9 9l-1 1m10 0l-1-1m-9-9l-1-1" />
                            </svg>
                        </span>
                        <span class="pm-sidebar-link-label">{{ __('Parcheggi') }}</span>
                    </a>

                    <a href="{{ route('operational-settings.edit') }}"
                        class="pm-sidebar-link {{ request()->routeIs('operational-settings.*') ? 'active' : '' }}"
                        title="{{ __('Fatturazione e stampa') }}">
                        <span class="pm-sidebar-link-icon">
                            <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="7.5" cy="7.5" r="2.2" />
                                <path d="M7.5 1.2v1.3m0 10v1.3M1.2 7.5h1.3m10 0h1.3M3 3l.9.9m7.2 7.2.9.9M12 3l-.9.9m-7.2 7.2-.9.9" />
                            </svg>
                        </span>
                        <span class="pm-sidebar-link-label">{{ __('Fatturazione e stampa') }}</span>
                    </a>
                @endcan
            </nav>

            <div class="pm-sidebar-footer">
                <x-locale-switcher compact />
                <div class="pm-sidebar-user">
                    <a href="{{ route('admin.account.edit') }}"
                       style="display:flex;align-items:center;gap:10px;flex:1;text-decoration:none;color:inherit">

                        <div class="pm-avatar" style="flex-shrink:0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>

                        <div class="pm-sidebar-user-info">
                            <div class="pm-sidebar-user-name">{{ auth()->user()->name }}</div>
                            <div class="pm-sidebar-user-role">{{ auth()->user()->role->label() }}</div>
                        </div>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" style="flex-shrink:0">
                        @csrf
                        <button type="submit" class="pm-sidebar-logout" title="{{ __('Esci') }}">
                            {{ __('Esci') }}
                        </button>
                    </form>
                </div>
            </div>

        </aside>

        {{-- CONTENUTO --}}
        <div class="pm-content" id="pm-content">

            <x-demo-banner />

            @if (isset($header))
                <div class="pm-topbar">
                    <button class="pm-mobile-menu-btn" onclick="toggleSidebarMobile()" title="{{ __('Menu') }}" aria-label="{{ __('Menu') }}">
                        <svg viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.5">
                            <line x1="2" y1="4" x2="13" y2="4"/>
                            <line x1="2" y1="7.5" x2="13" y2="7.5"/>
                            <line x1="2" y1="11" x2="13" y2="11"/>
                        </svg>
                    </button>
                    {{ $header }}
                </div>
            @endif

            <main class="pm-main">
                {{ $slot }}
            </main>

        </div>

    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('pm-sidebar');
            const content = document.getElementById('pm-content');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
            localStorage.setItem('pm-sidebar-collapsed', sidebar.classList.contains('collapsed'));
        }

        function toggleSidebarMobile() {
            const sidebar = document.getElementById('pm-sidebar');
            const overlay = document.getElementById('pm-sidebar-overlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        }

        // Ripristina stato al caricamento
        (function() {
            const collapsed = localStorage.getItem('pm-sidebar-collapsed') === 'true';
            if (collapsed && window.innerWidth > 1024) {
                document.getElementById('pm-sidebar').classList.add('collapsed');
                document.getElementById('pm-content').classList.add('collapsed');
            }
        })();
    </script>

</body>

</html>
