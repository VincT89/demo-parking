<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Prenotazioni') }}</h1>
            <div class="pm-page-subtitle">{{ __('Gestione prenotazioni multicanale') }}</div>
        </div>
        <a href="{{ route('reservations.create') }}" class="pm-btn pm-btn-primary">
            {{ __('Nuova prenotazione') }}
        </a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-mb-16 pm-animate">
        <form method="GET" action="{{ route('reservations.index') }}">
            <!-- Prima riga: Ricerca e dropdown generali -->
            <div class="pm-filters" style="margin-bottom: 16px;">
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="{{ __('Cerca cliente (premi Invio)...') }}" aria-label="{{ __('Cerca cliente') }}"
                    class="pm-input" style="flex: 2" onchange="this.form.submit()" />
                <select name="platform_id" class="pm-select" aria-label="{{ __('Canale') }}" onchange="this.form.submit()">
                    <option value="">{{ __('Tutti i canali') }}</option>
                    @foreach ($platforms as $platform)
                        <option value="{{ $platform->id }}" {{ request('platform_id') == $platform->id ? 'selected' : '' }}>
                            {{ __($platform->name) }}
                        </option>
                    @endforeach
                </select>
                <select name="status" class="pm-select" aria-label="{{ __('Stato') }}" onchange="this.form.submit()">
                    <option value="">{{ __('Tutti gli stati') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                <select name="sort_by" class="pm-select" aria-label="{{ __('Ordinamento') }}" onchange="this.form.submit()">
                    <option value="created_at_desc" {{ request('sort_by', 'created_at_desc') == 'created_at_desc' ? 'selected' : '' }}>{{ __('Data inserimento (più recenti)') }}</option>
                    <option value="starts_at_asc" {{ request('sort_by') == 'starts_at_asc' ? 'selected' : '' }}>{{ __('Data arrivo (crescente)') }}</option>
                    <option value="starts_at_desc" {{ request('sort_by') == 'starts_at_desc' ? 'selected' : '' }}>{{ __('Data arrivo (decrescente)') }}</option>
                    <option value="created_at_asc" {{ request('sort_by') == 'created_at_asc' ? 'selected' : '' }}>{{ __('Data inserimento (meno recenti)') }}</option>
                </select>
            </div>

            <!-- Seconda riga: Date e bottoni -->
            <div class="pm-filters" style="align-items: center;">
                <select name="date_filter_type" class="pm-select" aria-label="{{ __('Tipo di data') }}" onchange="this.form.submit()" style="max-width: 160px; flex: unset;">
                    <option value="starts_at" {{ request('date_filter_type', 'starts_at') === 'starts_at' ? 'selected' : '' }}>
                        {{ __('Filtra per: arrivo') }}
                    </option>
                    <option value="ends_at" {{ request('date_filter_type') === 'ends_at' ? 'selected' : '' }}>
                        {{ __('Filtra per: partenza') }}
                    </option>
                </select>
                
                <div style="display:flex; align-items:center; gap: 8px;">
                    <span style="font-size: 13px; color: var(--pm-text-muted); font-weight: 500;">{{ __('Dal') }}</span>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="pm-input" aria-label="{{ __('Dal') }}"
                        onchange="this.form.submit()" style="flex: unset; width: 140px;" />
                </div>
                
                <div style="display:flex; align-items:center; gap: 8px;">
                    <span style="font-size: 13px; color: var(--pm-text-muted); font-weight: 500;">{{ __('Al') }}</span>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="pm-input" aria-label="{{ __('Al') }}"
                        onchange="this.form.submit()" style="flex: unset; width: 140px;" />
                </div>

                <div style="margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="{{ route('reservations.index', array_merge(request()->except(['page', 'date_from', 'date_to', 'quick_filter']), [
                        'quick_filter' => 'arrivals_today',
                    ])) }}" class="pm-btn {{ request('quick_filter') === 'arrivals_today' ? 'pm-btn-primary' : 'pm-btn-secondary' }} pm-btn-sm" style="background: {{ request('quick_filter') === 'arrivals_today' ? 'var(--pm-accent)' : 'transparent' }};">
                        {{ __('Arrivi oggi') }}
                    </a>
                    <a href="{{ route('reservations.index', array_merge(request()->except(['page', 'date_from', 'date_to', 'quick_filter']), [
                        'quick_filter' => 'departures_today',
                    ])) }}" class="pm-btn {{ request('quick_filter') === 'departures_today' ? 'pm-btn-primary' : 'pm-btn-secondary' }} pm-btn-sm" style="background: {{ request('quick_filter') === 'departures_today' ? 'var(--pm-accent)' : 'transparent' }};">
                        {{ __('Partenze oggi') }}
                    </a>
                    <a href="{{ route('reservations.index') }}" class="pm-btn pm-btn-secondary pm-btn-sm" title="{{ __('Reimposta filtri') }}">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </a>
                    <a href="{{ route('reservations.export', request()->query()) }}" class="pm-btn pm-btn-sm" style="background: var(--pm-green); color: white; border-color: var(--pm-green);">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        {{ __('Esporta Excel') }}
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="pm-card pm-animate-2">
        <div class="pm-table-wrapper">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>{{ __('Cliente') }}</th>
                        <th>{{ __('Volo') }}</th>
                        <th>{{ __('Canale') }}</th>
                        <th>{{ __('Arrivo') }}</th>
                        <th>{{ __('Partenza') }}</th>
                        <th>{{ __('Posti') }}</th>
                        <th title="{{ __('Passeggeri navetta') }}">{{ __('Pax') }}</th>
                        <th>{{ __('Prezzo') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td>
                                <div class="pm-td-main">{{ $reservation->customer_name }}</div>
                            </td>
                            <td>
                                @if ($reservation->flight_reference)
                                    <span style="font-family: var(--pm-mono); font-size: 11px; font-weight: 600; color: var(--pm-accent); background: rgba(59, 130, 246, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2); display: inline-block;">
                                        {{ $reservation->flight_reference }}
                                    </span>
                                @else
                                    <span class="pm-text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="pm-platform">
                                    <div class="pm-dot blue"></div>
                                    {{ __($reservation->parkingListing->platform->name) }}
                                </div>
                            </td>
                            <td class="pm-mono">{{ $reservation->starts_at->format('d-m-Y H:i') }}</td>
                            <td class="pm-mono">{{ $reservation->ends_at->format('d-m-Y H:i') }}</td>
                            <td class="pm-mono">{{ $reservation->spots }}</td>
                            <td class="pm-mono">{{ $reservation->passengers_count ?? 1 }}</td>
                            <td class="pm-mono">
                                {{ $reservation->price ? '€ ' . number_format($reservation->price, 2) : '—' }}
                            </td>
                            <td>
                                @php
                                    $statusColor = match ($reservation->status) {
                                        \App\Enums\ReservationStatus::Confirmed => 'green',
                                        \App\Enums\ReservationStatus::Cancelled => 'red',
                                        \App\Enums\ReservationStatus::Pending => 'amber',
                                        default => 'gray',
                                    };
                                    $platformSlug = $reservation->parkingListing?->platform?->slug;
                                    $payment = $reservation->latestPayment;
                                @endphp
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                    <span class="pm-badge {{ $statusColor }}">
                                        {{ $reservation->status->label() }}
                                    </span>
                                    
                                    @if($platformSlug === 'website')
                                        @if($payment?->status === \App\Enums\PaymentStatus::Paid->value)
                                            <span class="pm-badge green">{{ __('Pagato') }}</span>
                                        @else
                                            <span class="pm-badge amber" style="color: #b45309; border-color: #fcd34d; background-color: #fffbeb;">{{ __('Da pagare') }}</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:8px;align-items:center">
                                    <a href="{{ route('reservations.show', $reservation) }}"
                                        class="pm-btn pm-btn-secondary pm-btn-sm" title="{{ __('Vedi dettagli') }}">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="{{ route('reservations.edit', $reservation) }}"
                                        class="pm-btn pm-btn-secondary pm-btn-sm" title="{{ __('Modifica') }}">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    @if ($reservation->status !== \App\Enums\ReservationStatus::Cancelled)
                                        <form method="POST" action="{{ route('reservations.destroy', $reservation) }}"
                                            onsubmit="return confirm(@js(__('Confermi la cancellazione?')))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="pm-btn pm-btn-danger pm-btn-sm" title="{{ __('Cancella') }}">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align:center;padding:32px 0;color:var(--pm-text-muted)">
                                {{ __('Nessuna prenotazione trovata.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reservations->hasPages())
            <div class="pm-pagination">
                {{ $reservations->links('vendor.pagination.pm') }}
            </div>
        @endif
    </div>

</x-app-layout>
