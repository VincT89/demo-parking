<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Blocchi disponibilità') }}</h1>
            <div class="pm-page-subtitle">{{ __('Chiusure, manutenzioni e blocchi manuali') }}</div>
        </div>
        <a href="{{ route('availability-blocks.create') }}" class="pm-btn pm-btn-primary">
            {{ __('Nuovo blocco') }}
        </a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-animate">
        <div class="pm-table-wrapper">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>{{ __('Tipo') }}</th>
                        <th>{{ __('Parcheggio') }}</th>
                        <th>{{ __('Canale') }}</th>
                        <th>{{ __('Dal') }}</th>
                        <th>{{ __('Al') }}</th>
                        <th>{{ __('Posti') }}</th>
                        <th>{{ __('Motivo') }}</th>
                        <th>{{ __('Creato da') }}</th>
                        <th>{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($blocks as $block)
                        <tr>
                            <td>
                                <span class="pm-badge gray">{{ $block->type->label() }}</span>
                            </td>
                            <td class="pm-td-main">{{ __($block->parking->name) }}</td>
                            <td>
                                <span class="pm-text-muted" style="font-size:13px">
                                    {{ $block->parkingListing?->platform ? __($block->parkingListing->platform->name) : __('Tutti i canali') }}
                                </span>
                            </td>
                            <td class="pm-mono">{{ $block->starts_at->format('d-m-Y H:i') }}</td>
                            <td class="pm-mono">{{ $block->ends_at->format('d-m-Y H:i') }}</td>
                            <td class="pm-mono">{{ $block->spots }}</td>
                            <td class="pm-text-muted" style="font-size:13px">
                                {{ $block->reason ?? '—' }}
                            </td>
                            <td class="pm-text-muted" style="font-size:13px">
                                {{ $block->createdBy->name }}
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('availability-blocks.destroy', $block) }}"
                                      onsubmit="return confirm(@js(__('Eliminare il blocco?'))) ">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="pm-btn pm-btn-danger pm-btn-sm">
                                        {{ __('Elimina') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align:center;padding:32px 0;color:var(--pm-text-muted)">
                                {{ __('Nessun blocco presente.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($blocks->hasPages())
            <div class="pm-pagination">
                {{ $blocks->links() }}
            </div>
        @endif
    </div>

</x-app-layout>
