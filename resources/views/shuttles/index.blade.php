<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Piano navette') }}</h1>
            <div class="pm-page-subtitle">{{ __('Gruppi calcolati dalle prenotazioni e dai passeggeri') }}</div>
        </div>
        @can('manage-parkings')<a href="{{ route('shuttles.settings.edit', ['parking_id' => $parking->id]) }}" class="pm-btn pm-btn-secondary">{{ __('Configura') }}</a>@endcan
    </x-slot>

    <x-flash-message />

    <div class="pm-shuttle-page">
        <div class="pm-card pm-shuttle-toolbar">
            <form method="GET" action="{{ route('shuttles.index') }}" class="pm-form-grid-2">
                <div class="pm-form-group"><label for="shuttle_parking" class="pm-label">{{ __('Parcheggio') }}</label><select id="shuttle_parking" name="parking_id" class="pm-select">@foreach($parkings as $item)<option value="{{ $item->id }}" @selected($item->is($parking))>{{ __($item->name) }}</option>@endforeach</select></div>
                <div class="pm-form-group"><label for="shuttle_date" class="pm-label">{{ __('Data operativa') }}</label><input id="shuttle_date" name="date" type="date" value="{{ $date->format('Y-m-d') }}" class="pm-input"></div>
                <div class="pm-form-actions pm-form-span-2"><button type="submit" class="pm-btn pm-btn-secondary">{{ __('Mostra giornata') }}</button></div>
            </form>
            <form method="POST" action="{{ route('shuttles.generate') }}" class="pm-shuttle-generate">
                @csrf
                <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                <button type="submit" class="pm-btn pm-btn-primary" @disabled(!$settings?->is_enabled)>{{ __('Calcola viaggi') }}</button>
                <p>{{ __('Aggiunge solo i passeggeri non ancora assegnati e non sovrascrive le modifiche manuali.') }}</p>
            </form>
        </div>

        @if(!$settings?->is_enabled)
            <div class="pm-notice pm-notice-danger pm-mt-20">{{ __('La pianificazione navette non è ancora attiva per questo parcheggio.') }} @can('manage-parkings')<a href="{{ route('shuttles.settings.edit', ['parking_id' => $parking->id]) }}">{{ __('Apri configurazione') }}</a>@endcan</div>
        @endif

        @if($unassigned->isNotEmpty())
            <section class="pm-card pm-mt-20">
                <div class="pm-card-header"><div><h2 class="pm-card-title">{{ __('Gruppi non assegnati') }}</h2><p class="pm-card-help">{{ __('Saranno inseriti dal prossimo calcolo oppure possono essere assegnati manualmente.') }}</p></div><span class="pm-badge amber">{{ $unassigned->sum('remaining') }} {{ __('pax') }}</span></div>
                <div class="pm-unassigned-list">
                    @foreach($unassigned as $item)
                        <div><span class="pm-mono">{{ $item['time']->format('H:i') }}</span><strong>{{ $item['reservation']->customer_name }}</strong><span>{{ $item['direction']->label() }}</span><span>{{ $item['remaining'] }} {{ __('pax') }}</span></div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="pm-shuttle-columns pm-mt-20">
            @foreach($directions as $direction)
                @php $directionTrips = $trips->get($direction->value, collect()); @endphp
                <section class="pm-shuttle-direction">
                    <div class="pm-shuttle-direction-header"><h2>{{ $direction->label() }}</h2><span>{{ trans_choice(':count viaggio|:count viaggi', $directionTrips->count(), ['count' => $directionTrips->count()]) }}</span></div>
                    <div class="pm-shuttle-trip-list">
                        @forelse($directionTrips as $trip)
                            @php
                                $tripStatusColor = match($trip->status->value) { 'confirmed', 'completed' => 'green', 'cancelled' => 'red', 'departed' => 'blue', default => 'amber' };
                                $eligible = $unassigned->filter(fn($item) => $item['direction'] === $direction);
                            @endphp
                            <article class="pm-card pm-shuttle-trip {{ $trip->status->value === 'cancelled' ? 'is-cancelled' : '' }}">
                                <div class="pm-shuttle-trip-head">
                                    <div><time datetime="{{ $trip->scheduled_at->toIso8601String() }}">{{ $trip->scheduled_at->format('H:i') }}</time><span>{{ $trip->vehicle?->name ?? __('Navetta da assegnare') }}</span></div>
                                    <span class="pm-badge {{ $tripStatusColor }}">{{ $trip->status->label() }}</span>
                                </div>
                                <div class="pm-shuttle-capacity"><span>{{ __('Posti occupati') }}</span><strong>{{ $trip->assigned_passengers }} / {{ $trip->capacity }}</strong></div>
                                <div class="pm-shuttle-passengers">
                                    @forelse($trip->assignments as $assignment)
                                        <div>
                                            <div><strong>{{ $assignment->reservation->customer_name }}</strong><span>{{ $assignment->passengers }} {{ __('pax') }} · {{ $assignment->reservation->flight_reference ?: __('volo non indicato') }}</span></div>
                                            @if($trip->status->value !== 'cancelled')<form method="POST" action="{{ route('shuttles.assignments.destroy', $assignment) }}">@csrf @method('DELETE')<button type="submit" class="pm-text-button">{{ __('Rimuovi') }}</button></form>@endif
                                        </div>
                                    @empty<div class="pm-shuttle-empty">{{ __('Nessun gruppo assegnato.') }}</div>@endforelse
                                </div>

                                @if($trip->status->value !== 'cancelled')
                                    <details class="pm-row-details pm-mt-16">
                                        <summary>{{ __('Modifica viaggio') }}</summary>
                                        <form method="POST" action="{{ route('shuttles.trips.update', $trip) }}" class="pm-form pm-mt-16">
                                            @csrf @method('PUT')
                                            <div class="pm-form-grid-2">
                                                <div class="pm-form-group"><label class="pm-label">{{ __('Orario') }}</label><input name="scheduled_at" type="datetime-local" value="{{ $trip->scheduled_at->format('Y-m-d\TH:i') }}" class="pm-input" required></div>
                                                <div class="pm-form-group"><label class="pm-label">{{ __('Navetta') }}</label><select name="shuttle_vehicle_id" class="pm-select"><option value="">{{ __('Da assegnare') }}</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected($trip->shuttle_vehicle_id === $vehicle->id)>{{ $vehicle->name }} · {{ $vehicle->seats }} {{ __('posti') }}</option>@endforeach</select></div>
                                                <div class="pm-form-group"><label class="pm-label">{{ __('Capienza') }}</label><input name="capacity" type="number" min="1" value="{{ $trip->capacity }}" class="pm-input" required></div>
                                                <div class="pm-form-group"><label class="pm-label">{{ __('Stato') }}</label><select name="status" class="pm-select">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($trip->status === $status)>{{ $status->label() }}</option>@endforeach</select></div>
                                                <div class="pm-form-group pm-form-span-2"><label class="pm-label">{{ __('Note') }}</label><textarea name="notes" class="pm-textarea">{{ $trip->notes }}</textarea></div>
                                            </div>
                                            <button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva modifiche') }}</button>
                                        </form>
                                    </details>

                                    @if($eligible->isNotEmpty() && $trip->remaining_seats > 0)
                                        <details class="pm-row-details pm-mt-16">
                                            <summary>{{ __('Assegna gruppo') }}</summary>
                                            <form method="POST" action="{{ route('shuttles.assignments.store', $trip) }}" class="pm-form pm-mt-16">
                                                @csrf
                                                <div class="pm-form-group"><label class="pm-label">{{ __('Prenotazione') }}</label><select name="reservation_id" class="pm-select">@foreach($eligible as $item)<option value="{{ $item['reservation']->id }}">{{ $item['time']->format('H:i') }} · {{ $item['reservation']->customer_name }} · {{ $item['remaining'] }} {{ __('pax') }}</option>@endforeach</select></div>
                                                <div class="pm-form-group"><label class="pm-label">{{ __('Passeggeri da assegnare') }}</label><input name="passengers" type="number" min="1" max="{{ $trip->remaining_seats }}" value="1" class="pm-input" required></div>
                                                <button class="pm-btn pm-btn-secondary" type="submit">{{ __('Assegna') }}</button>
                                            </form>
                                        </details>
                                    @endif

                                    <form method="POST" action="{{ route('shuttles.trips.cancel', $trip) }}" class="pm-shuttle-cancel" onsubmit="return confirm(@js(__('Annullare questo viaggio?')))" >@csrf<button class="pm-text-button is-danger" type="submit">{{ __('Annulla viaggio') }}</button></form>
                                @endif
                            </article>
                        @empty
                            <div class="pm-card pm-empty-state"><strong>{{ __('Nessun viaggio pianificato.') }}</strong><p>{{ __('Usa “Calcola viaggi” oppure crea un viaggio manualmente.') }}</p></div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <section class="pm-card pm-mt-20">
            <div class="pm-card-header"><div><h2 class="pm-card-title">{{ __('Nuovo viaggio manuale') }}</h2><p class="pm-card-help">{{ __('Puoi aggiungere corse extra senza modificare le proposte già create.') }}</p></div></div>
            <form method="POST" action="{{ route('shuttles.trips.store') }}" class="pm-form">
                @csrf
                <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                <input type="hidden" name="status" value="proposed">
                <div class="pm-form-grid-3">
                    <div class="pm-form-group"><label class="pm-label">{{ __('Direzione') }}</label><select name="direction" class="pm-select">@foreach($directions as $direction)<option value="{{ $direction->value }}">{{ $direction->label() }}</option>@endforeach</select></div>
                    <div class="pm-form-group"><label class="pm-label">{{ __('Data e ora') }}</label><input name="scheduled_at" type="datetime-local" value="{{ $date->copy()->setTime(8, 0)->format('Y-m-d\TH:i') }}" class="pm-input" required></div>
                    <div class="pm-form-group"><label class="pm-label">{{ __('Navetta') }}</label><select name="shuttle_vehicle_id" class="pm-select"><option value="">{{ __('Da assegnare') }}</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->name }} · {{ $vehicle->seats }} {{ __('posti') }}</option>@endforeach</select></div>
                    <div class="pm-form-group"><label class="pm-label">{{ __('Capienza se non assegni una navetta') }}</label><input name="capacity" type="number" min="1" value="{{ $settings?->default_capacity ?? 1 }}" class="pm-input" required></div>
                    <div class="pm-form-group pm-form-span-2"><label class="pm-label">{{ __('Note') }}</label><input name="notes" class="pm-input"></div>
                </div>
                <div class="pm-form-actions"><button class="pm-btn pm-btn-secondary" type="submit">{{ __('Crea viaggio') }}</button></div>
            </form>
        </section>
    </div>
</x-app-layout>
