<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Piattaforme') }}</h1>
            <div class="pm-page-subtitle">{{ __('Gestione canali di vendita e posti assegnati') }}</div>
        </div>

        <div class="pm-platform-actions">
            <button type="button" class="pm-btn pm-btn-secondary" onclick="document.getElementById('historical-sync-modal').classList.add('pm-modal-open')">
                {{ config('demo.enabled') ? __('Simula un periodo') : __('Recupera storico') }}
            </button>

            <form method="POST" action="{{ route('platforms.future-sync') }}" onsubmit="return confirm(@js(config('demo.enabled') ? __('Simulare una chiamata API per i prossimi giorni?') : __('Recuperare le prenotazioni da oggi ai prossimi 6 mesi?'))) ">
                @csrf
                <button type="submit" class="pm-btn pm-btn-secondary">
                    {{ config('demo.enabled') ? __('Prossimi giorni') : __('Prossimi 6 mesi') }}
                </button>
            </form>

            <form method="POST" action="{{ route('platforms.sync') }}" onsubmit="return confirm(@js(config('demo.enabled') ? __('Simulare ora la risposta delle API?') : __('Vuoi avviare il sync manuale?'))) ">
                @csrf
                <button type="submit" class="pm-btn pm-btn-primary">
                    {{ config('demo.enabled') ? __('Simula chiamata API') : __('Sincronizza piattaforme') }}
                </button>
            </form>

            <a href="{{ route('platforms.create') }}" class="pm-btn pm-btn-primary">
                {{ __('Nuova piattaforma') }}
            </a>
        </div>
    </x-slot>

    <div id="historical-sync-modal" class="pm-modal" role="dialog" aria-modal="true" aria-labelledby="historical-sync-title">
        <div class="pm-modal-card">
            <div class="pm-card-header">
                <div class="pm-card-title" id="historical-sync-title">{{ config('demo.enabled') ? __('Simula un periodo') : __('Recupera storico') }}</div>
                <button type="button" aria-label="{{ __('Chiudi') }}" onclick="document.getElementById('historical-sync-modal').classList.remove('pm-modal-open')" class="pm-modal-close">&times;</button>
            </div>
            <div class="pm-card-body">
                <p class="text-sm text-gray-500 mb-4">
                    {{ config('demo.enabled')
                        ? __('La simulazione è limitata al mese dimostrativo disponibile.')
                        : __('Recupera le prenotazioni con entrata, uscita o permanenza nel periodo selezionato.') }}
                </p>
                <form method="POST" action="{{ route('platforms.historical-sync') }}" class="pm-form">
                    @csrf
                    <div class="pm-field" style="margin-bottom:12px;">
                        <label class="pm-label" for="historical-sync-from">{{ __('Data inizio') }}</label>
                        <input type="date" id="historical-sync-from" name="from" class="pm-input" @if(config('demo.enabled')) min="{{ now()->subDays(config('demo.days_before', 7))->toDateString() }}" max="{{ now()->addDays(config('demo.days_after', 23))->toDateString() }}" @endif required>
                    </div>
                    <div class="pm-field" style="margin-bottom:16px;">
                        <label class="pm-label" for="historical-sync-to">{{ __('Data fine') }}</label>
                        <input type="date" id="historical-sync-to" name="to" class="pm-input" @if(config('demo.enabled')) min="{{ now()->subDays(config('demo.days_before', 7))->toDateString() }}" max="{{ now()->addDays(config('demo.days_after', 23))->toDateString() }}" @endif required>
                    </div>
                    <div class="pm-modal-actions">
                        <button type="button" class="pm-btn" onclick="document.getElementById('historical-sync-modal').classList.remove('pm-modal-open')">{{ __('Annulla') }}</button>
                        <button type="submit" class="pm-btn pm-btn-primary">{{ config('demo.enabled') ? __('Avvia simulazione') : __('Conferma recupero') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-flash-message />

    @if (!empty($lastSyncLog))
        <div class="pm-card pm-sync-summary">
            <div class="pm-card-header">
                <div>
                    <div class="pm-card-title">{{ __('Ultima sincronizzazione') }}</div>
                    <div class="pm-text-muted" style="font-size:12px">
                        {{ $lastSyncLog->created_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}
                        · {{ __('stato') }}: {{ __($lastSyncLog->status) }}
                        · {{ __('origine') }}: {{ __($lastSyncLog->source) }}
                    </div>
                </div>
            </div>

            <div class="pm-sync-stats">
                <div>{{ __('Create') }}: {{ $lastSyncLog->reservations_created }}</div>
                <div>{{ __('Aggiornate') }}: {{ $lastSyncLog->reservations_updated }}</div>
                <div>{{ __('Saltate') }}: {{ $lastSyncLog->reservations_skipped }}</div>
                <div>{{ __('Errori') }}: {{ $lastSyncLog->reservations_failed }}</div>
            </div>
        </div>
    @endif

    <div class="pm-gap">
        @forelse ($platforms as $platform)
            <div class="pm-card pm-animate">

                {{-- Header piattaforma --}}
                <div class="pm-platform-header">
                    <div class="pm-platform-info">
                        <div class="pm-platform-details">
                            <div class="pm-platform-title-row">
                                <span class="pm-platform-name">
                                    {{ __($platform->name) }}
                                </span>
                                <span class="pm-badge {{ $platform->is_active ? 'green' : 'red' }}">
                                    {{ $platform->is_active ? __('Attiva') : __('Inattiva') }}
                                </span>
                            </div>
                            <div class="pm-platform-meta">
                                <span class="pm-text-muted pm-text-mono">{{ $platform->slug }}</span>
                                @if ($platform->website)
                                    <a href="{{ $platform->website }}" target="_blank" class="pm-platform-link">
                                        {{ $platform->website }}
                                    </a>
                                @endif
                                @if ($platform->contact_email)
                                    <span class="pm-text-muted" style="font-size:12px">
                                        {{ $platform->contact_email }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="pm-platform-actions-sm">
                        <a href="{{ route('platforms.edit', $platform) }}"
                           class="pm-btn pm-btn-secondary pm-btn-sm">
                            {{ __('Modifica') }}
                        </a>
                        <form method="POST"
                              action="{{ route('platforms.destroy', $platform) }}"
                              onsubmit="return confirm(@js(__('Disattivare la piattaforma?'))) ">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pm-btn pm-btn-danger pm-btn-sm">
                                {{ __('Disattiva') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div style="border-top:1px solid var(--pm-border);padding-top:16px;margin-top:16px;">
                    <div style="font-size:12px;font-weight:500;color:var(--pm-text-muted);
                                text-transform:uppercase;letter-spacing:0.08em;
                                font-family:var(--pm-mono);margin-bottom:12px">
                        {{ __('Parcheggi connessi') }}
                    </div>
                    @if ($platform->listings->isNotEmpty())
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            @foreach ($platform->listings as $listing)
                                <span class="pm-badge" style="background:rgba(255,255,255,0.05);color:var(--pm-text);border:1px solid var(--pm-border);">
                                    {{ __($listing->parking->name) }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <div style="font-size:13px;color:var(--pm-text-muted)">
                            {{ __('Nessun parcheggio associato a questa piattaforma. Apri Modifica per collegarne uno.') }}
                        </div>
                    @endif
                </div>

            </div>
        @empty
            <div class="pm-card pm-animate">
                <p class="pm-text-muted" style="font-size:13px;text-align:center;padding:16px 0">
                    {{ __('Nessuna piattaforma configurata.') }}
                </p>
            </div>
        @endforelse
    </div>

</x-app-layout>
