<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Biglietto :reference', ['reference' => $reservation->external_id]) }}</title>
    <style>
        :root { --paper-width: {{ $paperWidth }}mm; --paper-height: {{ $paperHeight }}mm; --blue: #245fd6; --ink: #172033; --muted: #5f687b; --line: #d9deea; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f6; color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: end; gap: 12px; flex-wrap: wrap; padding: 14px 20px; background: #fff; border-bottom: 1px solid var(--line); }
        .toolbar a { color: var(--ink); text-decoration: none; font-size: 14px; padding: 9px 12px; border: 1px solid var(--line); border-radius: 6px; }
        .toolbar form { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; flex: 1; }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field label { color: var(--muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        select, input, button { min-height: 38px; border: 1px solid var(--line); border-radius: 6px; background: #fff; color: var(--ink); padding: 7px 10px; font: inherit; }
        input { width: 82px; }
        button { cursor: pointer; font-weight: 700; }
        button.primary { margin-left: auto; background: var(--blue); border-color: var(--blue); color: #fff; padding-inline: 18px; }
        .preview { display: grid; place-items: start center; padding: 28px; min-height: calc(100vh - 70px); overflow: auto; }
        .preview-stage { width: min(var(--paper-width), 100%); }
        .sheet { width: var(--paper-width); height: var(--paper-height); overflow: hidden; transform-origin: top left; background: #fff; box-shadow: 0 8px 32px rgba(26, 38, 66, .14); padding: min(8mm, 6vw); display: flex; flex-direction: column; }
        .ticket-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; padding-bottom: 5mm; border-bottom: .3mm solid var(--line); }
        .logo { width: 44mm; max-width: 45%; height: 13mm; object-fit: contain; object-position: left center; filter: brightness(0) saturate(100%) invert(34%) sepia(94%) saturate(1579%) hue-rotate(211deg) brightness(96%) contrast(91%); }
        .parking { text-align: right; min-width: 0; }
        .parking strong, .parking span { display: block; overflow-wrap: anywhere; }
        .parking strong { font-size: 12pt; }
        .parking span { color: var(--muted); font-size: 8pt; margin-top: 1.5mm; }
        h1 { margin: 6mm 0 1.5mm; font-size: 17pt; line-height: 1.15; }
        .subtitle { margin: 0 0 6mm; color: var(--muted); font-size: 9pt; }
        .reference { margin-bottom: 6mm; padding: 4mm; border: .5mm solid var(--blue); text-align: center; }
        .reference span { display: block; color: var(--muted); font-size: 7pt; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .reference strong { display: block; margin-top: 1.5mm; color: var(--blue); font-family: Consolas, monospace; font-size: 20pt; overflow-wrap: anywhere; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm 6mm; margin: 0; }
        .details div { min-width: 0; }
        .details dt { color: var(--muted); font-size: 7pt; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .details dd { margin: 1mm 0 0; font-size: 11pt; font-weight: 700; overflow-wrap: anywhere; }
        .plate dd { display: inline-block; padding: 1.5mm 2mm; border: .3mm solid var(--line); border-radius: 1mm; font-family: Consolas, monospace; font-size: 14pt; }
        .footer { margin-top: auto; padding-top: 6mm; color: var(--muted); font-size: 7.5pt; line-height: 1.4; white-space: pre-line; }
        .demo { margin-top: 4mm; padding-top: 3mm; border-top: .3mm solid var(--line); color: #6a4a00; font-size: 7pt; font-weight: 700; }
        .format-label .sheet, .format-custom .sheet { padding: 2.5mm; }
        .format-label .ticket-head, .format-custom .ticket-head { padding-bottom: 1mm; }
        .format-label .logo, .format-custom .logo { width: 24mm; height: 6mm; }
        .format-label .parking strong, .format-custom .parking strong { font-size: 7pt; }
        .format-label .parking span, .format-custom .parking span { display: none; }
        .format-label h1, .format-custom h1 { margin: 1.5mm 0 .5mm; font-size: 9pt; }
        .format-label .subtitle, .format-custom .subtitle { display: none; }
        .format-label .reference, .format-custom .reference { margin-bottom: 1.5mm; padding: 1.25mm; }
        .format-label .reference strong, .format-custom .reference strong { margin-top: .4mm; font-size: 11pt; }
        .format-label .details, .format-custom .details { gap: 1mm 2mm; }
        .format-label .details dt, .format-custom .details dt { font-size: 4.75pt; }
        .format-label .details dd, .format-custom .details dd { margin-top: .25mm; font-size: 6.5pt; }
        .format-label .plate dd, .format-custom .plate dd { padding: .35mm .75mm; font-size: 7.5pt; }
        .format-label .footer, .format-custom .footer { padding-top: 1mm; font-size: 4.75pt; line-height: 1.2; }
        .format-label .demo, .format-custom .demo { margin-top: 1mm; padding-top: 1mm; font-size: 4.5pt; line-height: 1.2; }
        .fit-warning { flex-basis: 100%; margin: 0; color: #9f1d2b; font-size: 12px; font-weight: 700; line-height: 1.4; }
        @media (max-width: 680px) {
            .toolbar { align-items: stretch; padding: 12px; }
            .toolbar > a { width: 100%; text-align: center; }
            .toolbar form { width: 100%; }
            .field { flex: 1 1 120px; }
            select, .field input { width: 100%; }
            button { flex: 1 1 140px; }
            button.primary { margin-left: 0; }
            .preview { padding: 14px; }
            .sheet { box-shadow: 0 4px 18px rgba(26, 38, 66, .12); }
        }
        @page { size: {{ $paperWidth }}mm {{ $paperHeight }}mm; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .preview { display: block; min-height: 0; padding: 0; overflow: visible; }
            .preview-stage { width: {{ $paperWidth }}mm !important; height: {{ $paperHeight }}mm !important; }
            .sheet { width: {{ $paperWidth }}mm; height: {{ $paperHeight }}mm; min-height: 0; transform: none !important; box-shadow: none; overflow: hidden; }
        }
    </style>
</head>
<body class="format-{{ $format }}">
    <div class="toolbar" aria-label="{{ __('Impostazioni di stampa') }}">
        <a href="{{ route('reservations.show', $reservation) }}">{{ __('Torna alla prenotazione') }}</a>
        <form method="GET" action="{{ route('reservations.ticket', $reservation) }}">
            <div class="field">
                <label for="format">{{ __('Formato') }}</label>
                <select id="format" name="format">
                    <option value="a4" @selected($format === 'a4')>A4</option>
                    <option value="a6" @selected($format === 'a6')>A6</option>
                    <option value="label" @selected($format === 'label')>{{ __('Etichetta') }}</option>
                    <option value="custom" @selected($format === 'custom')>{{ __('Personalizzato') }}</option>
                </select>
            </div>
            <div class="field">
                <label for="orientation">{{ __('Orientamento') }}</label>
                <select id="orientation" name="orientation">
                    <option value="portrait" @selected($orientation === 'portrait')>{{ __('Verticale') }}</option>
                    <option value="landscape" @selected($orientation === 'landscape')>{{ __('Orizzontale') }}</option>
                </select>
            </div>
            <div class="field custom-size">
                <label for="width">{{ __('Larghezza mm') }}</label>
                <input id="width" name="width" type="number" min="30" max="216" value="{{ $customWidth }}">
            </div>
            <div class="field custom-size">
                <label for="height">{{ __('Altezza mm') }}</label>
                <input id="height" name="height" type="number" min="30" max="356" value="{{ $customHeight }}">
            </div>
            <button type="submit">{{ __('Aggiorna anteprima') }}</button>
            <button class="primary" type="button" onclick="window.print()">{{ __('Stampa') }}</button>
            <p class="fit-warning" id="fit-warning" role="status" hidden>{{ __('Il contenuto non entra nel formato scelto. Aumenta le dimensioni o usa un foglio più grande.') }}</p>
        </form>
    </div>

    <main class="preview">
        <div class="preview-stage">
        <article class="sheet">
            <header class="ticket-head">
                @if($settings->ticket_show_logo)
                    <img class="logo" src="{{ asset('img/sodano-consulting-source.png') }}" alt="{{ config('demo.brand_name', 'Sodano Consulting') }}">
                @endif
                <div class="parking">
                    <strong>{{ __($reservation->parking->name) }}</strong>
                    @if($reservation->parking->address)<span>{{ $reservation->parking->address }}</span>@endif
                </div>
            </header>

            <h1>{{ __($settings->ticket_title) }}</h1>
            <p class="subtitle">{{ __('Presentare questo biglietto al ritiro del veicolo.') }}</p>

            <div class="reference">
                <span>{{ __('Riferimento prenotazione') }}</span>
                <strong>{{ $reservation->external_id }}</strong>
            </div>

            <dl class="details">
                <div class="plate"><dt>{{ __('Targa') }}</dt><dd>{{ $reservation->license_plate ?: '—' }}</dd></div>
                <div><dt>{{ __('Cliente') }}</dt><dd>{{ $reservation->customer_name }}</dd></div>
                <div><dt>{{ __('Ingresso') }}</dt><dd>{{ $reservation->starts_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt>{{ __('Ritiro previsto') }}</dt><dd>{{ $reservation->ends_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt>{{ __('Tipologia') }}</dt><dd>{{ __($reservation->parkingProduct?->name ?? '—') }}</dd></div>
                <div><dt>{{ __('Passeggeri') }}</dt><dd>{{ $reservation->passengers_count ?? 1 }}</dd></div>
            </dl>

            @if($settings->ticket_footer)
                <footer class="footer">{{ __($settings->ticket_footer) }}</footer>
            @endif
            @if(config('demo.enabled'))
                <div class="demo">{{ __('DEMO — Documento non fiscale con dati dimostrativi') }}</div>
            @endif
        </article>
        </div>
    </main>

    <script>
        (() => {
            const format = document.getElementById('format');
            const fields = document.querySelectorAll('.custom-size');
            const sync = () => fields.forEach(field => field.hidden = !['label', 'custom'].includes(format.value));
            format.addEventListener('change', sync);
            sync();

            const preview = document.querySelector('.preview');
            const stage = document.querySelector('.preview-stage');
            const sheet = document.querySelector('.sheet');
            const fitWarning = document.getElementById('fit-warning');
            const syncPreviewScale = () => {
                sheet.style.transform = 'none';
                fitWarning.hidden = sheet.scrollHeight <= sheet.clientHeight + 1;
                const previewStyle = getComputedStyle(preview);
                const availableWidth = preview.clientWidth
                    - parseFloat(previewStyle.paddingLeft)
                    - parseFloat(previewStyle.paddingRight);
                const scale = Math.min(1, availableWidth / sheet.offsetWidth);
                sheet.style.transform = scale < 1 ? `scale(${scale})` : 'none';
                stage.style.width = `${Math.ceil(sheet.offsetWidth * scale)}px`;
                stage.style.height = `${Math.ceil(sheet.offsetHeight * scale)}px`;
            };
            window.addEventListener('resize', syncPreviewScale);
            window.addEventListener('afterprint', syncPreviewScale);
            syncPreviewScale();

            @if($autoprint)
                window.addEventListener('load', () => setTimeout(() => window.print(), 250));
            @endif
        })();
    </script>
</body>
</html>
