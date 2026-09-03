<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Analisi redditività') }}</h1>
            <div class="pm-page-subtitle">{{ $monthLabel }}</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
            <a href="{{ $prevMonthUrl }}" class="pm-btn pm-btn-secondary pm-btn-sm" title="{{ __('Mese precedente') }}">&#8249; {{ __('Mese precedente') }}</a>
            <a href="{{ $nextMonthUrl }}" class="pm-btn pm-btn-secondary pm-btn-sm" title="{{ __('Mese successivo') }}">{{ __('Mese successivo') }} &#8250;</a>
        </div>
    </x-slot>

    <x-flash-message />

    <style>
        .pm-analytics-card { overflow: visible !important; }
    </style>

    {{-- Totali --}}
    <div class="pm-stats-grid pm-mb-16 pm-animate">
        <div class="pm-stat">
            <div class="pm-stat-label">{{ __('Entrate mese') }}</div>
            <div class="pm-stat-value green">€ {{ number_format($totals['revenue'], 2) }}</div>
            <div class="pm-stat-delta">{{ __('tutte le piattaforme') }}</div>
        </div>
        <div class="pm-stat">
            <div class="pm-stat-label">{{ __('Prenotazioni') }}</div>
            <div class="pm-stat-value blue">{{ $totals['count'] }}</div>
            <div class="pm-stat-delta">{{ __('questo mese') }}</div>
        </div>
        <div class="pm-stat">
            <div class="pm-stat-label">{{ __('Prezzo medio') }}</div>
            <div class="pm-stat-value amber">€ {{ number_format($totals['avg_price'], 2) }}</div>
            <div class="pm-stat-delta">{{ __('per prenotazione') }}</div>
        </div>
        <div class="pm-stat">
            <div class="pm-stat-label">{{ __('Cancellate') }}</div>
            <div class="pm-stat-value red">{{ $totals['cancelled'] }}</div>
            <div class="pm-stat-delta">{{ __('questo mese') }}</div>
        </div>
    </div>

    {{-- Canali --}}
    <div class="pm-gap">
        @foreach ($channelStats as $stat)
            <div class="pm-card pm-analytics-card pm-animate-2">
                <div class="pm-analytics-grid">

                    {{-- Nome canale --}}
                    <div>
                        <div style="font-size:15px;font-weight:600;color:var(--pm-text);margin-bottom:4px">
                            {{ $stat['platform'] }}
                        </div>
                        <div class="pm-text-muted pm-text-mono" style="font-size:12px">
                            {{ __('Piattaforma connessa') }}
                        </div>
                    </div>

                    {{-- Entrate --}}
                    <div>
                        <div class="pm-stat-label">{{ __('Entrate') }}</div>
                        <div
                            style="font-size:22px;font-weight:600;color:var(--pm-green);font-family:var(--pm-mono);letter-spacing:-0.02em">
                            € {{ number_format($stat['this_revenue'], 2) }}
                        </div>
                        @if ($stat['revenue_change'] !== null)
                            <div
                                style="font-size:12px;margin-top:4px;color:{{ $stat['revenue_change'] >= 0 ? 'var(--pm-green)' : 'var(--pm-red)' }}">
                                {{ $stat['revenue_change'] >= 0 ? '+' : '' }}{{ $stat['revenue_change'] }}%
                                <span class="pm-text-muted">{{ __('rispetto al mese scorso') }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Prenotazioni --}}
                    <div>
                        <div class="pm-stat-label">{{ __('Prenotazioni') }}</div>
                        <div
                            style="font-size:22px;font-weight:600;color:var(--pm-accent);font-family:var(--pm-mono);letter-spacing:-0.02em">
                            {{ $stat['this_count'] }}
                        </div>
                        @if ($stat['count_change'] !== null)
                            <div
                                style="font-size:12px;margin-top:4px;color:{{ $stat['count_change'] >= 0 ? 'var(--pm-green)' : 'var(--pm-red)' }}">
                                {{ $stat['count_change'] >= 0 ? '+' : '' }}{{ $stat['count_change'] }}%
                                <span class="pm-text-muted">{{ __('rispetto al mese scorso') }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Prezzo medio --}}
                    <div>
                        <div class="pm-stat-label">{{ __('Prezzo medio') }}</div>
                        <div
                            style="font-size:22px;font-weight:600;color:var(--pm-amber);font-family:var(--pm-mono);letter-spacing:-0.02em">
                            € {{ number_format($stat['avg_price'], 2) }}
                        </div>
                        <div style="font-size:12px;margin-top:4px" class="pm-text-muted">
                            {{ __(':count cancellate', ['count' => $stat['cancelled']]) }}
                        </div>
                    </div>

                    {{-- Trend sparkline --}}
                    <div>
                        <div class="pm-stat-label" style="margin-bottom:8px">{{ __('Trend annuale') }}</div>
                        <div class="pm-trend-bars">
                            @php
                                $maxRevenue = max(array_column($stat['trend'], 'revenue')) ?: 1;
                            @endphp
                            @foreach ($stat['trend'] as $i => $t)
                                @php
                                    $height = $maxRevenue > 0 ? max(6, round(($t['revenue'] / $maxRevenue) * 50)) : 6;
                                    $isCurrent = $t['is_current'] ?? false;
                                @endphp
                                <div style="display:flex;flex-direction:column;align-items:center;gap:3px;flex:1">
                                    <div class="pm-trend-bar-wrap">
                                        <div
                                            class="pm-trend-bar {{ $isCurrent ? 'is-current' : '' }}"
                                            style="height: {{ $height }}px;"
                                        >
                                            <div class="pm-trend-tooltip">
                                                <div class="pm-trend-tooltip-month">{{ $t['month'] }}</div>
                                                <div class="pm-trend-tooltip-value">€ {{ number_format($t['revenue'], 2) }}</div>
                                                <div class="pm-trend-tooltip-meta">{{ __(':count prenotazioni', ['count' => $t['count']]) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                
                                    <div style="font-size:9px;font-family:var(--pm-mono);color:var(--pm-text-dim)">
                                        {{ $t['month'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        @endforeach
    </div>

</x-app-layout>
