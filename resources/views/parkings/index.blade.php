<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Parcheggi') }}</h1>
            <div class="pm-page-subtitle">{{ __('Gestione sedi e configurazioni globali') }}</div>
        </div>
        <a href="{{ route('parkings.create') }}" class="pm-btn pm-btn-primary">
            {{ __('Nuovo parcheggio') }}
        </a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-animate">
        <div class="pm-table-wrapper">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Posti totali') }}</th>
                        <th>{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($parkings as $parking)
                        <tr>
                            <td>
                                <div class="pm-td-main">{{ __($parking->name) }}</div>
                            </td>
                            <td>
                                @if ($parking->is_active)
                                    <span class="pm-badge green">{{ __('Attivo') }}</span>
                                @else
                                    <span class="pm-badge gray">{{ __('Disattivato') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="pm-mono">{{ $parking->total_spots }}</span>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <a href="{{ route('parkings.edit', $parking) }}" class="pm-btn pm-btn-secondary pm-btn-sm">
                                        {{ __('Modifica e configura') }}
                                    </a>
                                    
                                    @if ($parking->is_active)
                                        <form method="POST" action="{{ route('parkings.destroy', $parking) }}" onsubmit="return confirm('{{ __('Disattivare questo parcheggio?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="pm-btn pm-btn-danger pm-btn-sm">
                                                {{ __('Disattiva') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="pm-text-muted" style="text-align:center; padding:32px;">
                                {{ __('Nessun parcheggio presente nel sistema.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
