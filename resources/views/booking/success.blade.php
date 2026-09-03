@extends('layouts.public')

@section('content')
    <style>
        .pm-card-success {
            max-width: 640px;
            margin: 0 auto;
        }
        @media print {
            body { background: white !important; }
            .pm-card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
            .no-print { display: none !important; }
        }
    </style>

    <div class="pm-card pm-card-success">
        
        <!-- Header Success -->
        <div class="pm-card-header" style="text-align: center; border-bottom: 1px solid var(--pm-border); padding: 32px 20px 24px; background: #f8fafc;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #16a34a; margin-bottom: 16px;">
                <svg style="width: 32px; height: 32px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="pm-card-title" style="font-size: 24px; color: #0f172a; margin-bottom: 8px;">{{ __('Prenotazione confermata') }}</h2>
            <div style="font-size: 15px; color: var(--pm-text-muted);">
                {{ __('Prenotazione dimostrativa confermata. Nessun pagamento reale è stato eseguito.') }}
            </div>
        </div>

        <div style="padding: 32px;">
            
            <!-- Reservation Code -->
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 32px; padding: 20px; background: #f0fdf4; border: 1px dashed #bbf7d0; border-radius: 12px;">
                <span style="font-size: 13px; font-weight: 600; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">{{ __('Codice prenotazione') }}</span>
                <span style="font-family: monospace; font-size: 28px; font-weight: 700; color: #14532d; letter-spacing: 2px;">{{ $reservation->external_id }}</span>
            </div>

            <!-- Summary Details -->
            <div style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 32px;">
                
                <!-- Itinerary -->
                <div>
                    <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid var(--pm-border); padding-bottom: 8px;">{{ __('Dettagli sosta') }}</h3>
                    <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                        <svg style="width: 20px; height: 20px; color: var(--pm-text-muted); margin-right: 12px; margin-top: 2px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <div>
                            <div style="font-weight: 500; color: #334155;">{{ __($reservation->parking->name ?? 'Parcheggio principale') }}</div>
                            <div style="font-size: 14px; color: var(--pm-text-muted);">{{ __($reservation->parkingProduct->name ?? 'Servizio parcheggio') }} ({{ $reservation->spots }} {{ $reservation->spots > 1 ? __('veicoli') : __('veicolo') }})</div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                        <svg style="width: 20px; height: 20px; color: var(--pm-text-muted); margin-right: 12px; margin-top: 2px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; width: 100%;">
                            <div>
                                <div style="font-size: 12px; color: var(--pm-text-muted); text-transform: uppercase;">{{ __('Arrivo') }}</div>
                                <div style="font-weight: 500; color: #334155;">{{ $reservation->starts_at->format('d/m/Y H:i') }}</div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: var(--pm-text-muted); text-transform: uppercase;">{{ __('Partenza') }}</div>
                                <div style="font-weight: 500; color: #334155;">{{ $reservation->ends_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Data -->
                <div>
                    <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 16px; border-bottom: 1px solid var(--pm-border); padding-bottom: 8px;">{{ __('Dati cliente') }}</h3>
                    <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                        <svg style="width: 20px; height: 20px; color: var(--pm-text-muted); margin-right: 12px; margin-top: 2px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <div>
                            <div style="font-weight: 500; color: #334155;">{{ $reservation->customer_name }}</div>
                            <div style="font-size: 14px; color: var(--pm-text-muted);">{{ $reservation->customer_email }}</div>
                            <div style="font-size: 14px; color: var(--pm-text-muted);">{{ $reservation->customer_phone }}</div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; margin-top: 8px; margin-left: 32px;">
                        <span style="font-size: 12px; color: var(--pm-text-muted); text-transform: uppercase; margin-right: 8px; font-weight: 600;">{{ __('Targa') }}:</span>
                        <div style="font-family: monospace; font-weight: 700; font-size: 16px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 4px 12px; border-radius: 6px; color: #0f172a; letter-spacing: 1px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            {{ strtoupper($reservation->license_plate) }}
                        </div>

                        @if ($reservation->flight_reference)
                            <span style="font-size: 12px; color: var(--pm-text-muted); text-transform: uppercase; margin-right: 8px; margin-left: 20px; font-weight: 600;">{{ __('Volo') }}:</span>
                            <div style="font-family: monospace; font-weight: 700; font-size: 16px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 4px 12px; border-radius: 6px; color: #0f172a; letter-spacing: 1px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                {{ $reservation->flight_reference }}
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            <!-- Total Paid -->
            <div style="background: var(--pm-bg-soft); border: 1px solid var(--pm-border); border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                <div>
                    <div style="color: var(--pm-text-muted); font-size: 13px; font-weight: 600; text-transform: uppercase;">{{ __('Importo dimostrativo') }}</div>
                </div>
                <div style="font-size: 28px; font-weight: 700; color: #0f172a;">
                    € {{ number_format($reservation->price, 2, ',', '.') }}
                </div>
            </div>

            <!-- CTAs -->
            <div class="no-print" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <button onclick="window.print()" class="pm-btn pm-btn-secondary" style="display: flex; justify-content: center; align-items: center; width: 100%;">
                    <svg style="width: 20px; height: 20px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    {{ __('Stampa ricevuta') }}
                </button>
                <a href="{{ config('demo.brand_url') }}" class="pm-btn pm-btn-primary" style="display: flex; justify-content: center; align-items: center; width: 100%; text-decoration: none;">
                    <svg style="width: 20px; height: 20px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    {{ __('Visita Sodano Consulting') }}
                </a>
            </div>

        </div>
    </div>
@endsection
