@extends('layouts.public')

@section('content')
    <style>
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        .pm-card-payment {
            max-width: 560px;
            margin: 0 auto;
        }
    </style>

    <div class="pm-card pm-card-payment">
        <div class="pm-card-header" style="text-align: center; border-bottom: 1px solid var(--pm-border); padding-bottom: 20px;">
            <h2 class="pm-card-title">{{ __('Completa la prenotazione demo') }}</h2>
            <div style="font-size: 14px; color: var(--pm-text-muted); margin-top: 6px;">
                {{ __('Prenotazione') }} <span style="background: var(--pm-bg-soft); color: var(--pm-text-dark); padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: 600;">{{ $reservation->external_id }}</span>
            </div>
        </div>

        <div style="padding: 24px 32px;">
            
            <!-- Timer Alert -->
            <div id="timer-container" style="background: #fffbeb; border: 1px solid #fef3c7; color: #b45309; padding: 12px 16px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; transition: all 0.3s;">
                <div style="display: flex; align-items: center; font-size: 14px; font-weight: 500;">
                    <svg class="animate-pulse" style="width: 18px; height: 18px; margin-right: 8px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ __('Tempo rimanente per la conferma') }}
                </div>
                <div id="countdown" style="font-family: monospace; font-weight: 700; font-size: 18px;">15:00</div>
            </div>

            <div id="expired-alert" style="display: none; background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 24px; border-radius: 8px; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 24px; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                    <svg style="width: 24px; height: 24px; margin-right: 8px; color: #ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span style="font-weight: 600; font-size: 16px;">{{ __('Tempo scaduto') }}</span>
                </div>
                <p style="font-size: 14px; margin-bottom: 16px; opacity: 0.9;">{{ __('Questa prenotazione è stata annullata automaticamente.') }}</p>
                
                <a href="{{ route('public.booking.form', ['fresh' => time()]) }}" style="display: inline-flex; align-items: center; background: white; color: #334155; border: 1px solid #cbd5e1; padding: 10px 16px; border-radius: 6px; font-weight: 500; font-size: 14px; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                    <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    {{ __('Ripeti la prenotazione') }}
                </a>
            </div>

            <!-- Summary -->
            <div style="background: var(--pm-bg-soft); border: 1px solid var(--pm-border); border-radius: 8px; padding: 20px; margin-bottom: 32px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pm-border); padding-bottom: 16px; margin-bottom: 16px;">
                    <span style="color: var(--pm-text-muted); font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Importo dimostrativo') }}</span>
                    <span style="font-size: 28px; font-weight: 700; color: var(--pm-text-dark);">€ {{ number_format($reservation->price, 2, ',', '.') }}</span>
                </div>
                <div style="font-size: 13px; color: var(--pm-text-muted); text-align: center; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 16px; height: 16px; color: #16a34a; margin-right: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ __('Nessun pagamento viene elaborato in modalità demo') }}
                </div>
            </div>

            <!-- Error Container -->
            <div id="error-container" style="display: none; background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-bottom: 24px;"></div>

            <!-- Payment Options -->
            <div id="payment-options">
                
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <strong style="display: block; margin-bottom: 8px; font-size: 15px;">{{ __('Conferma dimostrativa') }}</strong>
                    <span style="font-size: 14px;">{{ __('La conferma aggiorna soltanto il database locale della demo. Carte, PayPal ed email reali sono disattivati.') }}</span>
                </div>

                <form method="POST" action="{{ route('public.booking.onsite.confirm', $reservation->external_id) }}">
                    @csrf
                    <button type="submit" class="pm-btn pm-btn-primary" style="width: 100%; padding: 14px; font-size: 16px; display: flex; justify-content: center; align-items: center;">
                        <svg style="width: 20px; height: 20px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        {{ __('Conferma prenotazione demo') }}
                    </button>
                </form>
                
            </div>
        </div>
        
        <div style="background: var(--pm-bg-soft); border-top: 1px solid var(--pm-border); padding: 16px; text-align: center;">
            <span style="font-size: 13px; font-weight: 500; color: var(--pm-text-muted);">{{ __('Ambiente dimostrativo Sodano Consulting') }}</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const timerContainer = document.getElementById('timer-container');
            const expiredAlert = document.getElementById('expired-alert');
            const countdownEl = document.getElementById('countdown');
            const paymentOptions = document.getElementById('payment-options');

            // Timer Logic
            const expiresAtTimestamp = {{ $reservation->expires_at ? $reservation->expires_at->timestamp * 1000 : 0 }};
            
            function updateTimer() {
                if (!expiresAtTimestamp) return;

                const now = new Date().getTime();
                const distance = expiresAtTimestamp - now;

                if (distance <= 0) {
                    clearInterval(timerInterval);
                    expireUI();
                    return;
                }

                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                countdownEl.innerHTML = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
                if (distance < 60000) { // last minute
                    countdownEl.style.color = '#dc2626'; // red-600
                    countdownEl.classList.add('animate-pulse');
                }
            }

            function expireUI() {
                timerContainer.style.display = 'none';
                expiredAlert.style.display = 'flex';
                paymentOptions.style.display = 'none';
            }

            const timerInterval = setInterval(updateTimer, 1000);
            updateTimer();

        });
    </script>
@endsection
