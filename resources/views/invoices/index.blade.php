<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Fatture elettroniche') }}</h1>
            <div class="pm-page-subtitle">{{ __('Archivio invii Aruba e simulazioni') }}</div>
        </div>
        @if(auth()->user()->isAdmin())
            <a class="pm-btn pm-btn-secondary" href="{{ route('operational-settings.edit') }}">{{ __('Configura') }}</a>
        @endif
    </x-slot>

    <div class="pm-animate">
        <x-flash-message />

        <div class="pm-card" style="margin-bottom:20px">
            <form class="pm-invoice-filters" method="GET" action="{{ route('invoices.index') }}">
                <div class="pm-form-group">
                    <label class="pm-label" for="parking_id">{{ __('Parcheggio') }}</label>
                    <select class="pm-select" id="parking_id" name="parking_id">
                        <option value="">{{ __('Tutti i parcheggi') }}</option>
                        @foreach($parkings as $parking)
                            <option value="{{ $parking->id }}" @selected((string) request('parking_id') === (string) $parking->id)>{{ __($parking->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pm-form-group">
                    <label class="pm-label" for="status">{{ __('Stato') }}</label>
                    <select class="pm-select" id="status" name="status">
                        <option value="">{{ __('Tutti gli stati') }}</option>
                        @foreach(['draft' => __('Bozza'), 'processing' => __('Presa in carico'), 'submitted' => __('Inviata a SdI'), 'delivered' => __('Consegnata'), 'rejected' => __('Scartata'), 'error' => __('Errore')] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="pm-btn pm-btn-primary" type="submit">{{ __('Filtra') }}</button>
                <a class="pm-btn pm-btn-secondary" href="{{ route('invoices.index') }}">{{ __('Reimposta') }}</a>
            </form>
        </div>

        <div class="pm-card pm-table-card">
            @if($invoices->isEmpty())
                <div class="pm-empty-state">
                    <strong>{{ __('Nessuna fattura presente.') }}</strong>
                    <p>{{ __('La prima bozza può essere creata dal dettaglio di una prenotazione.') }}</p>
                </div>
            @else
                <div class="pm-table-wrap">
                    <table class="pm-table pm-invoices-table">
                        <thead>
                            <tr>
                                <th>{{ __('Numero') }}</th>
                                <th>{{ __('Cliente') }}</th>
                                <th>{{ __('Prenotazione') }}</th>
                                <th>{{ __('Totale') }}</th>
                                <th>{{ __('Ambiente') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th><span class="pm-sr-only">{{ __('Azioni') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td data-label="{{ __('Numero') }}"><strong class="pm-mono">{{ $invoice->number }}</strong><small>{{ $invoice->created_at->format('d/m/Y H:i') }}</small></td>
                                    <td data-label="{{ __('Cliente') }}">{{ $invoice->customer_name }}</td>
                                    <td data-label="{{ __('Prenotazione') }}"><a href="{{ route('reservations.show', $invoice->reservation) }}">{{ $invoice->reservation->external_id }}</a></td>
                                    <td data-label="{{ __('Totale') }}">€ {{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</td>
                                    <td data-label="{{ __('Ambiente') }}">{{ match($invoice->provider_mode) { 'simulator' => __('Simulatore locale'), 'aruba_demo' => __('Aruba DEMO'), default => __('Aruba produzione') } }}</td>
                                    <td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $invoice->statusColor() }}">{{ $invoice->statusLabel() }}</span></td>
                                    <td class="pm-table-action"><a class="pm-btn pm-btn-secondary pm-btn-sm" href="{{ route('invoices.show', $invoice) }}">{{ __('Apri') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pm-pagination">{{ $invoices->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
