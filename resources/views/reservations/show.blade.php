<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Dettaglio prenotazione') }}</h1>
            <div class="pm-page-subtitle">{{ $reservation->external_id }}</div>
        </div>
        <div class="pm-header-actions">
            <a href="{{ route('reservations.ticket', $reservation) }}" class="pm-btn pm-btn-secondary">
                {{ __('Stampa biglietto') }}
            </a>
            <a href="{{ $reservation->electronicInvoice ? route('invoices.show', $reservation->electronicInvoice) : route('invoices.create', $reservation) }}" class="pm-btn pm-btn-secondary">
                {{ $reservation->electronicInvoice ? __('Apri fattura') : __('Crea fattura') }}
            </a>
            <a href="{{ route('reservations.index') }}" class="pm-btn pm-btn-secondary">
                {{ __('Torna alla lista') }}
            </a>
            <a href="{{ route('reservations.edit', $reservation) }}" class="pm-btn pm-btn-primary">
                {{ __('Modifica') }}
            </a>
        </div>
    </x-slot>

    <div class="pm-card pm-animate" style="max-width:800px; padding: 32px;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--pm-border-color);">
            <div>
                <h2 style="font-size: 20px; font-weight: 600; color: var(--pm-text); margin-bottom: 8px;">{{ $reservation->customer_name }}</h2>
                @if ($reservation->customer_email)
                    <div style="color: var(--pm-text-muted); font-size: 14px;">{{ $reservation->customer_email }}</div>
                @endif
                @if ($reservation->customer_phone)
                    <div style="color: var(--pm-text-muted); font-size: 14px;">{{ $reservation->customer_phone }}</div>
                @endif
            </div>
            <div style="text-align: right;">
                @php
                    $statusColor = match ($reservation->status) {
                        \App\Enums\ReservationStatus::Confirmed => 'green',
                        \App\Enums\ReservationStatus::Cancelled => 'red',
                        \App\Enums\ReservationStatus::Pending => 'amber',
                        default => 'gray',
                    };
                @endphp
                <span class="pm-badge {{ $statusColor }}" style="font-size: 14px; padding: 6px 12px; margin-bottom: 12px; display: inline-block;">
                    {{ $reservation->status->label() }}
                </span>
                <div style="font-family: var(--pm-mono); font-size: 13px; color: var(--pm-text-muted);">
                    {{ __('Inserita il :date', ['date' => $reservation->created_at->format('d/m/Y H:i')]) }}
                </div>
            </div>
        </div>

        <div class="pm-form-grid-2" style="margin-bottom: 32px;">
            <div>
                <div class="pm-label">{{ __('Canale di vendita') }}</div>
                <div style="font-weight: 500; font-size: 15px; color: var(--pm-text);">
                    <div class="pm-platform" style="display: inline-flex;">
                        <div class="pm-dot blue"></div>
                        {{ $reservation->parkingListing?->platform ? __($reservation->parkingListing->platform->name) : 'N/A' }}
                    </div>
                </div>
            </div>
            <div>
                <div class="pm-label">{{ __('Categoria / Tipologia posto') }}</div>
                <div style="font-weight: 500; font-size: 15px; color: var(--pm-text);">
                    {{ __($reservation->parkingProduct?->name ?? 'Nessuna categoria') }}
                </div>
            </div>
        </div>

        <div class="pm-form-grid-2" style="margin-bottom: 32px; background: var(--pm-bg-soft); padding: 16px; border-radius: 8px; border: 1px solid var(--pm-border-color);">
            <div>
                <div class="pm-label">{{ __('Arrivo previsto') }}</div>
                <div style="font-weight: 600; font-size: 16px; color: var(--pm-text);">
                    {{ $reservation->starts_at->format('d/m/Y') }} <span style="color: var(--pm-text-muted);">{{ __('alle') }}</span> {{ $reservation->starts_at->format('H:i') }}
                </div>
            </div>
            <div>
                <div class="pm-label">{{ __('Partenza prevista') }}</div>
                <div style="font-weight: 600; font-size: 16px; color: var(--pm-text);">
                    {{ $reservation->ends_at->format('d/m/Y') }} <span style="color: var(--pm-text-muted);">{{ __('alle') }}</span> {{ $reservation->ends_at->format('H:i') }}
                </div>
            </div>
        </div>

        <div class="pm-reservation-grid-4" style="margin-bottom: 32px;">
            <div>
                <div class="pm-label">{{ __('Veicolo / Targa') }}</div>
                @if($reservation->license_plate)
                    <div style="font-family: var(--pm-mono); font-weight: 600; font-size: 15px; color: var(--pm-text); background: var(--pm-bg-soft); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--pm-border-color); display: inline-block;">
                        {{ $reservation->license_plate }}
                    </div>
                @else
                    <span class="pm-text-muted">-</span>
                @endif
            </div>
            <div>
                <div class="pm-label">{{ __('Riferimento volo') }}</div>
                @if($reservation->flight_reference)
                    <span style="font-family: var(--pm-mono); font-size: 15px; font-weight: 600; color: var(--pm-accent); background: rgba(59, 130, 246, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.2); display: inline-block;">
                        {{ $reservation->flight_reference }}
                    </span>
                @else
                    <span class="pm-text-muted">-</span>
                @endif
            </div>
            <div>
                <div class="pm-label">{{ __('Posti occupati') }}</div>
                <div style="font-weight: 600; font-size: 18px; color: var(--pm-text);">
                    {{ $reservation->spots }}
                </div>
            </div>
            <div>
                <div class="pm-label">{{ __('Passeggeri navetta') }}</div>
                <div style="font-weight: 600; font-size: 18px; color: var(--pm-text);">
                    {{ $reservation->passengers_count ?? 1 }}
                </div>
            </div>
        </div>

        <div class="pm-form-grid-2" style="margin-bottom: 32px;">
            <div>
                <div class="pm-label">{{ __('Totale pratica') }}</div>
                <div style="font-weight: 700; font-size: 20px; color: var(--pm-green);">
                    € {{ number_format($reservation->price, 2) }}
                </div>
            </div>
        </div>

        @php
            $platformSlug = $reservation->parkingListing?->platform?->slug;
            $payment = $reservation->latestPayment;
        @endphp

        @if($platformSlug === 'website')
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--pm-border-color);">
                <div class="pm-label" style="font-size: 16px; font-weight: 600; color: var(--pm-text); margin-bottom: 16px;">{{ __('Stato pagamento') }}</div>
                
                @if($payment?->status === \App\Enums\PaymentStatus::Paid->value)
                    <span class="pm-badge green" style="padding: 6px 12px; font-size: 14px;">{{ __('Pagato') }}</span>

                    @if($payment->paid_at)
                        <div style="margin-top: 8px; font-size: 14px; color: var(--pm-text-muted);">
                            {{ __('Pagato il: :date', ['date' => \Carbon\Carbon::parse($payment->paid_at)->format('d/m/Y H:i')]) }}
                        </div>
                    @endif
                @else
                    <span class="pm-badge amber" style="padding: 6px 12px; font-size: 14px;">{{ __('Pagamento in attesa') }}</span>

                    <form method="POST" action="{{ route('reservations.mark-paid', $reservation) }}" style="margin-top: 16px;">
                        @csrf
                        <button type="submit" class="pm-btn pm-btn-primary" style="background-color: var(--pm-green); border-color: var(--pm-green);"
                                onclick="return confirm(@js(__('Confermi che il cliente ha pagato in struttura?'))) ">
                            {{ __('Segna come pagato') }}
                        </button>
                    </form>
                @endif

                <div style="margin-top: 16px; font-size: 14px; color: var(--pm-text-muted);">
                    {{ __('Metodo') }}: <strong style="color: var(--pm-text);">{{ ucfirst($payment?->provider ?? 'onsite') }}</strong><br>
                    {{ __('Importo') }}: <strong style="color: var(--pm-text);">€ {{ number_format($payment?->amount ?? $reservation->price, 2, ',', '.') }}</strong>
                </div>
            </div>
        @endif

        @if ($reservation->notes)
            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--pm-border-color);">
                <div class="pm-label">{{ __('Note operative') }}</div>
                <div style="background: #fffbea; border: 1px solid #fef08a; padding: 16px; border-radius: 8px; color: #854d0e; font-size: 14px; line-height: 1.5; white-space: pre-wrap;">{{ $reservation->notes }}</div>
            </div>
        @endif

    </div>

</x-app-layout>
