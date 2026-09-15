<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Configurazione navette') }}</h1>
            <div class="pm-page-subtitle">{{ __('Capienza, raggruppamento e mezzi disponibili') }}</div>
        </div>
        <a href="{{ route('shuttles.index', ['parking_id' => $parking->id]) }}" class="pm-btn pm-btn-secondary">{{ __('Torna al piano') }}</a>
    </x-slot>

    <x-flash-message />

    <div class="pm-settings-page">
        <div class="pm-card pm-settings-selector">
            <form method="GET" action="{{ route('shuttles.settings.edit') }}" class="pm-inline-filter">
                <div class="pm-form-group"><label for="shuttle_settings_parking" class="pm-label">{{ __('Parcheggio') }}</label><select id="shuttle_settings_parking" name="parking_id" class="pm-select" onchange="this.form.submit()">@foreach($parkings as $item)<option value="{{ $item->id }}" @selected($item->is($parking))>{{ __($item->name) }}</option>@endforeach</select></div>
            </form>
        </div>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header pm-settings-card-header"><div><h2 class="pm-card-title">{{ __('Regole di pianificazione') }}</h2><p class="pm-card-help">{{ __('Il calcolo crea proposte; orari, mezzo e gruppi restano sempre modificabili.') }}</p></div></div>
            <form method="POST" action="{{ route('shuttles.settings.update', $parking) }}" class="pm-form">
                @csrf @method('PUT')
                <label class="pm-switch-row"><input type="checkbox" name="is_enabled" value="1" class="pm-checkbox" @checked(old('is_enabled', $settings->is_enabled))><span><strong>{{ __('Gestione navetta attiva') }}</strong><small>{{ __('Abilita il calcolo dei viaggi per questo parcheggio.') }}</small></span></label>
                <div class="pm-form-grid-3">
                    <div class="pm-form-group"><label for="default_capacity" class="pm-label">{{ __('Capienza predefinita') }}</label><input id="default_capacity" name="default_capacity" type="number" min="1" max="100" value="{{ old('default_capacity', $settings->default_capacity ?? 1) }}" class="pm-input" required><small class="pm-field-help">{{ __('Usata quando nessun mezzo è assegnato.') }}</small></div>
                    <div class="pm-form-group"><label for="grouping_window" class="pm-label">{{ __('Finestra raggruppamento') }}</label><div class="pm-input-suffix"><input id="grouping_window" name="grouping_window_minutes" type="number" min="0" max="180" value="{{ old('grouping_window_minutes', $settings->grouping_window_minutes ?? 30) }}" class="pm-input" required><span>{{ __('min') }}</span></div></div>
                    <div class="pm-form-group"><label for="turnaround" class="pm-label">{{ __('Tempo minimo tra viaggi') }}</label><div class="pm-input-suffix"><input id="turnaround" name="turnaround_minutes" type="number" min="0" max="360" value="{{ old('turnaround_minutes', $settings->turnaround_minutes ?? 45) }}" class="pm-input" required><span>{{ __('min') }}</span></div></div>
                    <div class="pm-form-group"><label for="outbound_offset" class="pm-label">{{ __('Partenza dopo ingresso') }}</label><div class="pm-input-suffix"><input id="outbound_offset" name="outbound_offset_minutes" type="number" min="-180" max="180" value="{{ old('outbound_offset_minutes', $settings->outbound_offset_minutes ?? 15) }}" class="pm-input" required><span>{{ __('min') }}</span></div></div>
                    <div class="pm-form-group"><label for="return_offset" class="pm-label">{{ __('Rientro rispetto all’uscita') }}</label><div class="pm-input-suffix"><input id="return_offset" name="return_offset_minutes" type="number" min="-180" max="180" value="{{ old('return_offset_minutes', $settings->return_offset_minutes ?? 0) }}" class="pm-input" required><span>{{ __('min') }}</span></div></div>
                </div>
                <div class="pm-form-actions"><button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva configurazione') }}</button></div>
            </form>
        </section>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header pm-settings-card-header"><div><h2 class="pm-card-title">{{ __('Aggiungi navetta') }}</h2><p class="pm-card-help">{{ __('La capienza del mezzo viene usata automaticamente nelle proposte.') }}</p></div></div>
            <form method="POST" action="{{ route('shuttles.vehicles.store') }}" class="pm-form">
                @csrf
                <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                <div class="pm-form-grid-3">
                    <div class="pm-form-group"><label for="vehicle_name" class="pm-label pm-label-required">{{ __('Nome mezzo') }}</label><input id="vehicle_name" name="name" class="pm-input" required></div>
                    <div class="pm-form-group"><label for="vehicle_plate" class="pm-label">{{ __('Targa') }}</label><input id="vehicle_plate" name="license_plate" class="pm-input pm-uppercase"></div>
                    <div class="pm-form-group"><label for="vehicle_seats" class="pm-label pm-label-required">{{ __('Posti a bordo') }}</label><input id="vehicle_seats" name="seats" type="number" min="1" max="100" class="pm-input" required></div>
                    <div class="pm-form-group pm-form-span-2"><label for="vehicle_notes" class="pm-label">{{ __('Note') }}</label><input id="vehicle_notes" name="notes" class="pm-input"></div>
                    <label class="pm-switch-row"><input type="checkbox" name="is_active" value="1" class="pm-checkbox" checked><span><strong>{{ __('Mezzo attivo') }}</strong></span></label>
                </div>
                <div class="pm-form-actions"><button class="pm-btn pm-btn-primary" type="submit">{{ __('Aggiungi navetta') }}</button></div>
            </form>
        </section>

        <section class="pm-rate-list" aria-label="{{ __('Navette configurate') }}">
            @forelse($vehicles as $vehicle)
                <article class="pm-card">
                    <form method="POST" action="{{ route('shuttles.vehicles.update', $vehicle) }}" class="pm-form">
                        @csrf @method('PUT')
                        <div class="pm-card-header"><div><h2 class="pm-card-title">{{ $vehicle->name }}</h2><p class="pm-card-help">{{ $vehicle->license_plate ?: __('Targa non indicata') }}</p></div><span class="pm-badge {{ $vehicle->is_active ? 'green' : 'gray' }}">{{ $vehicle->is_active ? __('Attiva') : __('Disattivata') }}</span></div>
                        <div class="pm-form-grid-3">
                            <div class="pm-form-group"><label class="pm-label">{{ __('Nome mezzo') }}</label><input name="name" value="{{ $vehicle->name }}" class="pm-input" required></div>
                            <div class="pm-form-group"><label class="pm-label">{{ __('Targa') }}</label><input name="license_plate" value="{{ $vehicle->license_plate }}" class="pm-input pm-uppercase"></div>
                            <div class="pm-form-group"><label class="pm-label">{{ __('Posti a bordo') }}</label><input name="seats" type="number" min="1" max="100" value="{{ $vehicle->seats }}" class="pm-input" required></div>
                            <div class="pm-form-group pm-form-span-2"><label class="pm-label">{{ __('Note') }}</label><input name="notes" value="{{ $vehicle->notes }}" class="pm-input"></div>
                            <label class="pm-switch-row"><input type="checkbox" name="is_active" value="1" class="pm-checkbox" @checked($vehicle->is_active)><span><strong>{{ __('Mezzo attivo') }}</strong></span></label>
                        </div>
                        <div class="pm-form-actions"><button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva modifiche') }}</button></div>
                    </form>
                    @if($vehicle->is_active)<form method="POST" action="{{ route('shuttles.vehicles.destroy', $vehicle) }}" class="pm-rate-disable-form" onsubmit="return confirm(@js(__('Disattivare questa navetta?')))" >@csrf @method('DELETE')<button type="submit" class="pm-btn pm-btn-danger">{{ __('Disattiva') }}</button></form>@endif
                </article>
            @empty<div class="pm-card pm-empty-state"><strong>{{ __('Nessuna navetta configurata.') }}</strong><p>{{ __('Puoi comunque usare la capienza predefinita, ma il viaggio resterà senza mezzo assegnato.') }}</p></div>@endforelse
        </section>
    </div>
</x-app-layout>
