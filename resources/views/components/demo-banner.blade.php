@if (config('demo.enabled'))
    <div class="pm-demo-banner" role="status">
        <strong>{{ __('Modalità demo') }}</strong>
        <span>{{ __('Dati sintetici e API simulate. Nessuna operazione coinvolge sistemi reali.') }}</span>
    </div>
@endif
