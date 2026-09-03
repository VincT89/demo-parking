<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title calendar-page-title">
                {{ $type === 'entries' ? __('Veicoli in entrata') : __('Veicoli in uscita') }}
            </h1>
            <div class="pm-page-subtitle">
                {{ $date->format('d/m/Y') }}
            </div>
        </div>
    </x-slot>

    <div style="display:flex;gap:8px;margin-bottom:24px" class="pm-animate calendar-actions">
        <a href="{{ route('calendar', ['parking_id' => $parkingId]) }}" class="pm-btn pm-btn-secondary">
            {{ __('Vista calendario') }}
        </a>
        <a href="{{ route('calendar.day', ['type' => 'entries', 'parking_id' => $parkingId, 'date' => $date->toDateString()]) }}" class="pm-btn {{ $type === 'entries' ? 'pm-btn-primary' : 'pm-btn-secondary' }}">
            {{ __('Entrate') }}
        </a>
        <a href="{{ route('calendar.day', ['type' => 'exits', 'parking_id' => $parkingId, 'date' => $date->toDateString()]) }}" class="pm-btn {{ $type === 'exits' ? 'pm-btn-primary' : 'pm-btn-secondary' }}">
            {{ __('Uscite') }}
        </a>
    </div>

    <div class="calendar-day-toolbar pm-animate">
        <div style="display:flex; align-items:baseline; gap:12px;">
            <div class="calendar-current-date" style="font-size:24px; font-weight:600; color:var(--pm-text); font-family:var(--pm-mono); line-height:1">
                {{ $date->format('d / m') }}
            </div>
        </div>
        <div class="calendar-date-actions" style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('calendar.day', ['type' => $type, 'parking_id' => $parkingId, 'date' => $date->copy()->subDay()->toDateString()]) }}" class="pm-btn pm-btn-secondary pm-btn-sm">◄ {{ __('Giorno prima') }}</a>
            <a href="{{ route('calendar.day', ['type' => $type, 'parking_id' => $parkingId, 'date' => now(config('app.timezone'))->toDateString()]) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Oggi') }}</a>
            <a href="{{ route('calendar.day', ['type' => $type, 'parking_id' => $parkingId, 'date' => $date->copy()->addDay()->toDateString()]) }}" class="pm-btn pm-btn-secondary pm-btn-sm">{{ __('Giorno dopo') }} ►</a>
            <a href="{{ route('calendar.day.export', ['type' => $type, 'parking_id' => $parkingId, 'date' => $date->toDateString()]) }}" class="pm-btn pm-btn-secondary pm-btn-sm" style="margin-left: 16px;">{{ __('Esporta Excel') }}</a>
        </div>
    </div>

    <div class="calendar-summary-row pm-animate">
        <div class="calendar-summary-card">
            <div class="calendar-summary-title">{{ $type === 'entries' ? __('Veicoli in entrata') : __('Veicoli in uscita') }}</div>
            <div class="calendar-summary-value">{{ $reservationsCount }}</div>
            <div class="calendar-summary-label">{{ __('Veicoli') }}</div>
        </div>
        <div class="calendar-summary-card">
            <div class="calendar-summary-title">{{ $type === 'entries' ? __('Clienti in entrata') : __('Clienti in uscita') }}</div>
            <div class="calendar-summary-value">{{ $reservations->sum(fn ($reservation) => $reservation->passengers_count ?? 1) }}</div>
            <div class="calendar-summary-label">{{ __('Clienti') }}</div>
        </div>
    </div>

    <div class="pm-animate-2">
        @if($reservations->isEmpty())
            <div class="pm-card" style="padding: 32px; text-align: center; color: var(--pm-text-muted);">
                {{ $type === 'entries' ? __('Nessun veicolo in entrata per questa data.') : __('Nessun veicolo in uscita per questa data.') }}
            </div>
        @else
            <div class="pm-card">
                <div class="pm-table-wrapper calendar-table-wrapper">
                    <table class="pm-table calendar-table">
                    <thead>
                        <tr>
                            <th>{{ __('Ora') }}</th>
                            <th>{{ __('Targa') }}</th>
                            <th>{{ __('Volo') }}</th>
                            <th>{{ __('Cliente') }}</th>
                            <th>{{ __('Telefono') }}</th>
                            <th>{{ __('Prodotto') }}</th>
                            <th>{{ __('Posti') }}</th>
                            <th title="{{ __('Passeggeri navetta') }}">{{ __('Pax') }}</th>
                            <th>{{ __('Stato') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservations as $reservation)
                            <tr>
                                <td class="pm-mono">
                                    {{ $type === 'entries' ? $reservation->starts_at->format('H:i') : $reservation->ends_at->format('H:i') }}
                                </td>
                                <td>
                                    @if($reservation->license_plate)
                                        <span style="font-family: var(--pm-mono); font-weight: 600; color: var(--pm-accent); background: rgba(59, 130, 246, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                            {{ $reservation->license_plate }}
                                        </span>
                                    @else
                                        <span class="pm-text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($reservation->flight_reference)
                                        <span style="font-family: var(--pm-mono); font-weight: 600; color: var(--pm-accent); background: rgba(59, 130, 246, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2);">
                                            {{ $reservation->flight_reference }}
                                        </span>
                                    @else
                                        <span class="pm-text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="pm-td-main">{{ $reservation->customer_name }}</div>
                                </td>
                                <td>
                                    @if($reservation->customer_phone)
                                        <a href="tel:{{ $reservation->customer_phone }}" style="color: var(--pm-accent); text-decoration: none; font-weight: 500;">
                                            {{ $reservation->customer_phone }}
                                        </a>
                                    @else
                                        <span class="pm-text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="pm-td-main">{{ __($reservation->parkingProduct->name ?? 'N/D') }}</div>
                                    <div class="pm-td-sub">{{ __($reservation->parking->name ?? 'N/D') }}</div>
                                </td>
                                <td class="pm-mono">
                                    {{ $reservation->spots }}
                                </td>
                                <td class="pm-mono">
                                    {{ $reservation->passengers_count ?? 1 }}
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" id="check_{{ $reservation->id }}" {{ ($type === 'entries' ? $reservation->has_entered : $reservation->has_exited) ? 'checked' : '' }} onchange="toggleMovement({{ $reservation->id }}, '{{ $type === 'entries' ? 'entered' : 'exited' }}', this.checked)" style="width: 18px; height: 18px; border-radius: 4px; border: 1px solid var(--pm-border); cursor: pointer;">
                                        <label for="check_{{ $reservation->id }}" style="font-size: 13px; color: var(--pm-text-muted); cursor: pointer; user-select: none; margin: 0;">
                                            {{ $type === 'entries'
                                                ? __(':vehicle entrato', ['vehicle' => __($reservation->parkingProduct->name ?? 'Veicolo')])
                                                : __(':vehicle uscito', ['vehicle' => __($reservation->parkingProduct->name ?? 'Veicolo')]) }}
                                        </label>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif
    </div>

    <script>
        function toggleMovement(reservationId, type, isChecked) {
            fetch(`/reservations/${reservationId}/toggle-movement`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    type: type,
                    value: isChecked
                })
            }).then(res => {
                if (!res.ok) {
                    alert(@js(__('Errore nel salvataggio dello stato.')));
                }
            }).catch(err => {
                console.error(err);
                alert(@js(__('Errore di rete nel salvataggio.')));
            });
        }
    </script>
</x-app-layout>
