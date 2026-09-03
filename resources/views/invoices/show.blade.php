<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Fattura :number', ['number' => $invoice->number]) }}</h1>
            <div class="pm-page-subtitle">{{ $invoice->reservation->external_id }}</div>
        </div>
        <div class="pm-header-actions">
            <a class="pm-btn pm-btn-secondary" href="{{ route('reservations.show', $invoice->reservation) }}">{{ __('Prenotazione') }}</a>
            <a class="pm-btn pm-btn-secondary" href="{{ route('invoices.index') }}">{{ __('Archivio fatture') }}</a>
        </div>
    </x-slot>

    <div class="pm-invoice-page pm-animate">
        <x-flash-message />

        @if($errors->any())
            <div class="pm-notice pm-notice-danger" role="alert">
                <strong>{{ __('Operazione non completata.') }}</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if($settings->isSimulator())
            <div class="pm-notice pm-notice-info">
                <strong>{{ __('Documento dimostrativo senza valore fiscale.') }}</strong>
                {{ __('Le chiamate mostrate sotto sono simulate localmente e non raggiungono Aruba o SdI.') }}
            </div>
        @elseif($settings->invoice_mode === 'aruba_production')
            <div class="pm-notice pm-notice-danger">
                <strong>{{ __('Ambiente Aruba di produzione.') }}</strong>
                {{ __('Il comando di invio trasmette realmente il documento; verifica i dati prima di procedere.') }}
            </div>
        @else
            <div class="pm-notice pm-notice-info">
                <strong>{{ __('Ambiente Aruba DEMO.') }}</strong>
                {{ __('È richiesto un account di prova Aruba accreditato e ancora valido.') }}
            </div>
        @endif

        <div class="pm-invoice-overview">
            <section class="pm-card pm-invoice-summary-card">
                <div class="pm-invoice-summary-head">
                    <div>
                        <span class="pm-label">{{ __('Numero documento') }}</span>
                        <h2>{{ $invoice->number }}</h2>
                    </div>
                    <span class="pm-badge {{ $invoice->statusColor() }}">{{ $invoice->statusLabel() }}</span>
                </div>

                <dl class="pm-definition-grid">
                    <div><dt>{{ __('Cliente') }}</dt><dd>{{ $invoice->customer_name }}</dd></div>
                    <div><dt>{{ __('Data') }}</dt><dd>{{ $invoice->created_at->format('d/m/Y') }}</dd></div>
                    <div><dt>{{ __('Imponibile') }}</dt><dd>€ {{ number_format((float) $invoice->taxable_amount, 2, ',', '.') }}</dd></div>
                    <div><dt>{{ __('IVA (:rate%)', ['rate' => number_format((float) $invoice->vat_rate, 2, ',', '.')]) }}</dt><dd>€ {{ number_format((float) $invoice->vat_amount, 2, ',', '.') }}</dd></div>
                    <div class="pm-definition-total"><dt>{{ __('Totale') }}</dt><dd>€ {{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</dd></div>
                </dl>

                <div class="pm-invoice-identifiers">
                    <div><span>{{ __('Ambiente') }}</span><strong>{{ match($settings->invoice_mode) { 'simulator' => __('Simulatore locale'), 'aruba_demo' => __('Aruba DEMO'), default => __('Aruba produzione') } }}</strong></div>
                    <div><span>{{ __('Riferimento provider') }}</span><strong>{{ $invoice->remote_id ?: '—' }}</strong></div>
                    <div><span>{{ __('Nome file Aruba') }}</span><strong>{{ $invoice->remote_filename ?: '—' }}</strong></div>
                    <div><span>{{ __('Identificativo SdI') }}</span><strong>{{ $invoice->sdi_id ?: '—' }}</strong></div>
                </div>

                @if($invoice->last_error)
                    <div class="pm-notice pm-notice-danger" style="margin-top:20px">
                        <strong>{{ __('Ultimo errore') }}</strong><br>{{ __($invoice->last_error) }}
                    </div>
                @endif
            </section>

            <aside class="pm-card pm-invoice-actions-card">
                <h2 class="pm-card-title">{{ __('Azioni') }}</h2>

                @if(in_array($invoice->status, ['draft', 'error', 'rejected'], true))
                    <form method="POST" action="{{ route('invoices.submit', $invoice) }}">
                        @csrf
                        <button class="pm-btn pm-btn-primary pm-btn-block" type="submit"
                            @if(!$settings->isSimulator()) onclick="return confirm(@js(__('Confermi l’invio del documento all’ambiente Aruba configurato?')))" @endif>
                            {{ $settings->isSimulator() ? __('Invia al simulatore') : __('Invia ad Aruba') }}
                        </button>
                    </form>
                @endif

                @if($settings->isSimulator() && in_array($invoice->status, ['processing', 'submitted'], true))
                    <p class="pm-card-help">{{ __('Scegli la risposta che il simulatore deve restituire.') }}</p>
                    <form method="POST" action="{{ route('invoices.simulate', $invoice) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="delivered">
                        <button class="pm-btn pm-btn-primary pm-btn-block" type="submit">{{ __('Simula consegna') }}</button>
                    </form>
                    <form method="POST" action="{{ route('invoices.simulate', $invoice) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="rejected">
                        <button class="pm-btn pm-btn-danger pm-btn-block" type="submit">{{ __('Simula scarto') }}</button>
                    </form>
                    <form method="POST" action="{{ route('invoices.simulate', $invoice) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="delivery_failed">
                        <button class="pm-btn pm-btn-secondary pm-btn-block" type="submit">{{ __('Simula mancata consegna') }}</button>
                    </form>
                @elseif(!$settings->isSimulator() && $invoice->submitted_at && !$invoice->isFinal())
                    <form method="POST" action="{{ route('invoices.refresh', $invoice) }}">
                        @csrf
                        <button class="pm-btn pm-btn-primary pm-btn-block" type="submit">{{ __('Aggiorna stato da Aruba') }}</button>
                    </form>
                @endif

                @if($invoice->xml_payload)
                    <a class="pm-btn pm-btn-secondary pm-btn-block" href="{{ route('invoices.xml', $invoice) }}">{{ __('Scarica XML FatturaPA') }}</a>
                @endif

                @if(auth()->user()->isAdmin())
                    <a class="pm-btn pm-btn-secondary pm-btn-block" href="{{ route('operational-settings.edit', ['parking_id' => $invoice->parking_id]) }}">{{ __('Apri configurazione') }}</a>
                @endif
            </aside>
        </div>

        <div class="pm-invoice-lower-grid">
            <section class="pm-card">
                <div class="pm-card-header"><h2 class="pm-card-title">{{ __('Dati destinatario') }}</h2></div>
                <dl class="pm-definition-list">
                    <div><dt>{{ __('Nome o ragione sociale') }}</dt><dd>{{ $invoice->customer_name }}</dd></div>
                    <div><dt>{{ __('Codice fiscale') }}</dt><dd>{{ $invoice->customer_fiscal_code ?: '—' }}</dd></div>
                    <div><dt>{{ __('Partita IVA') }}</dt><dd>{{ $invoice->customer_vat_number ? $invoice->customer_vat_country.$invoice->customer_vat_number : '—' }}</dd></div>
                    <div><dt>{{ __('Codice destinatario') }}</dt><dd>{{ $invoice->customer_recipient_code ?: '0000000' }}</dd></div>
                    <div><dt>{{ __('PEC destinatario') }}</dt><dd>{{ $invoice->customer_pec ?: '—' }}</dd></div>
                    <div><dt>{{ __('Indirizzo') }}</dt><dd>{{ $invoice->customer_address }}, {{ $invoice->customer_postal_code }} {{ $invoice->customer_city }} {{ $invoice->customer_province }} ({{ $invoice->customer_country }})</dd></div>
                </dl>
            </section>

            <section class="pm-card">
                <div class="pm-card-header"><h2 class="pm-card-title">{{ __('Cronologia API') }}</h2></div>
                <ol class="pm-api-timeline">
                    @foreach(array_reverse($invoice->status_history ?? []) as $entry)
                        <li>
                            <div class="pm-api-timeline-head">
                                <strong>{{ \App\Models\ElectronicInvoice::statusLabelFor($entry['status'] ?? '') }}</strong>
                                <time>{{ isset($entry['at']) ? \Carbon\Carbon::parse($entry['at'])->format('d/m/Y H:i:s') : '—' }}</time>
                            </div>
                            @if(!empty($entry['api']['method']))
                                <code>{{ $entry['api']['method'] }} {{ $entry['api']['path'] ?? '' }}</code>
                            @endif
                            @if(!empty($entry['message']))<p>{{ __($entry['message']) }}</p>@endif
                            @if(!empty($entry['api']['response']))
                                <details>
                                    <summary>{{ __('Mostra risposta') }}</summary>
                                    <pre>{{ json_encode($entry['api']['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>
</x-app-layout>
