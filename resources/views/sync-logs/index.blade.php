<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Log di Sincronizzazione') }}</h1>
            <div class="pm-page-subtitle">{{ __('Cronologia delle chiamate API simulate') }}</div>
        </div>
    </x-slot>

    <div class="pm-card pm-mb-16 pm-animate">
        <form method="GET" action="{{ route('sync-logs.index') }}" class="pm-filters">
            <select name="status" class="pm-select" aria-label="{{ __('Stato') }}">
                <option value="">{{ __('Tutti gli stati') }}</option>
                <option value="success" @selected(request('status') === 'success')>{{ __('Riuscita') }}</option>
                <option value="failed" @selected(request('status') === 'failed')>{{ __('Fallita') }}</option>
            </select>

            <select name="platform_id" class="pm-select" aria-label="{{ __('Piattaforma') }}">
                <option value="">{{ __('Tutte le piattaforme') }}</option>
                @php
                    $platforms = \App\Models\Platform::orderBy('name')->get();
                @endphp
                @foreach($platforms as $platform)
                    <option value="{{ $platform->id }}" @selected(request('platform_id') == $platform->id)>
                        {{ __($platform->name) }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="pm-btn pm-btn-primary">{{ __('Filtra') }}</button>

            @if(request()->anyFilled(['status', 'platform_id']))
                <a href="{{ route('sync-logs.index') }}" class="pm-btn pm-btn-secondary">{{ __('Reimposta') }}</a>
            @endif
        </form>
    </div>

    <div class="pm-card pm-animate-2">
        <div class="pm-table-wrapper">
            <table class="pm-table pm-sync-log-table">
                <thead>
                    <tr>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Piattaforma') }}</th>
                        <th>{{ __('Parcheggio') }}</th>
                        <th>{{ __('Risultato') }}</th>
                        <th>{{ __('Statistiche') }}</th>
                        <th>{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td data-label="{{ __('Data') }}" class="pm-text-mono">
                                {{ $log->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td data-label="{{ __('Piattaforma') }}">
                                <span class="pm-td-main">{{ $log->platform ? __($log->platform->name) : '-' }}</span>
                            </td>
                            <td data-label="{{ __('Parcheggio') }}">
                                {{ $log->parkingListing?->parking ? __($log->parkingListing->parking->name) : '-' }}
                            </td>
                            <td data-label="{{ __('Risultato') }}">
                                <div class="pm-sync-log-status">
                                    <span class="pm-badge {{ $log->status === 'success' ? 'green' : 'red' }}">
                                        {{ $log->status === 'success' ? __('Riuscita') : __('Fallita') }}
                                    </span>
                                    @if($log->is_dry_run)
                                        <span class="pm-badge gray">{{ __('Simulazione') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="{{ __('Statistiche') }}">
                                <div class="pm-sync-log-stats">
                                    <span title="{{ __('Create') }}">C:{{ $log->reservations_created }}</span>
                                    <span title="{{ __('Aggiornate') }}">U:{{ $log->reservations_updated }}</span>
                                    <span title="{{ __('Saltate') }}">S:{{ $log->reservations_skipped }}</span>
                                    <span title="{{ __('Fallite') }}">F:{{ $log->reservations_failed }}</span>
                                </div>
                            </td>
                            <td data-label="{{ __('Note') }}" class="pm-sync-log-notes" title="{{ $log->notes }}">
                                {{ $log->notes ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr class="pm-sync-log-empty">
                            <td colspan="6" class="pm-text-muted">{{ __('Nessun log di sincronizzazione trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="pm-pagination">
                {{ $logs->links('vendor.pagination.pm') }}
            </div>
        @endif
    </div>
</x-app-layout>
