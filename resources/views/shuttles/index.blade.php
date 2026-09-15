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

        <section class="pm-card pm-table-card pm-shuttle-plan pm-mt-20">
            <div class="pm-card-header pm-shuttle-table-header">
                <div><h2 class="pm-card-title">{{ __('Piano navette') }}</h2><p class="pm-card-help">{{ __('Gruppi calcolati dalle prenotazioni e dai passeggeri') }}</p></div>
                <span>{{ trans_choice(':count viaggio|:count viaggi', $trips->count(), ['count' => $trips->count()]) }}</span>
            </div>
            <form method="GET" action="{{ route('shuttles.index') }}" class="pm-shuttle-filters">
                <input type="hidden" name="parking_id" value="{{ $parking->id }}">
                <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                <div class="pm-form-group">
                    <label for="shuttle_direction_filter" class="pm-label">{{ __('Direzione') }}</label>
                    <select id="shuttle_direction_filter" name="direction" class="pm-select">
                        <option value="">{{ __('Tutte le direzioni') }}</option>
                        @foreach($directions as $direction)<option value="{{ $direction->value }}" @selected(request('direction') === $direction->value)>{{ $direction->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="pm-form-group">
                    <label for="shuttle_status_filter" class="pm-label">{{ __('Stato') }}</label>
                    <select id="shuttle_status_filter" name="status" class="pm-select">
                        <option value="">{{ __('Tutti gli stati') }}</option>
                        @foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="pm-form-group">
                    <label for="shuttle_vehicle_filter" class="pm-label">{{ __('Navetta') }}</label>
                    <select id="shuttle_vehicle_filter" name="vehicle" class="pm-select">
                        <option value="">{{ __('Tutte le navette') }}</option>
                        <option value="unassigned" @selected(request('vehicle') === 'unassigned')>{{ __('Da assegnare') }}</option>
                        @foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) request('vehicle') === (string) $vehicle->id)>{{ $vehicle->name }}</option>@endforeach
                    </select>
                </div>
                <div class="pm-form-group">
                    <label for="shuttle_search_filter" class="pm-label">{{ __('Cerca') }}</label>
                    <input id="shuttle_search_filter" name="search" value="{{ request('search') }}" class="pm-input" placeholder="{{ __('Cerca cliente o volo') }}">
                </div>
                <div class="pm-shuttle-filter-actions">
                    <button type="submit" class="pm-btn pm-btn-primary">{{ __('Filtra') }}</button>
                    <a href="{{ route('shuttles.index', ['parking_id' => $parking->id, 'date' => $date->format('Y-m-d')]) }}" class="pm-btn pm-btn-secondary">{{ __('Reimposta') }}</a>
                </div>
            </form>
            <div class="pm-table-wrap">
                <table class="pm-table pm-responsive-table pm-shuttle-table">
                    <thead>
                        <tr>
                            <th>{{ __('Orario') }}</th>
                            <th>{{ __('Direzione') }}</th>
                            <th>{{ __('Navetta') }}</th>
                            <th>{{ __('Prenotazioni') }}</th>
                            <th>{{ __('Posti occupati') }}</th>
                            <th>{{ __('Stato') }}</th>
                            <th><span class="pm-sr-only">{{ __('Azioni') }}</span></th>
                        </tr>
                    </thead>
                    <tbody x-data="{ openTrip: null }" @keydown.escape.window="openTrip = null">
                        @forelse($trips as $trip)
                            @php
                                $tripStatusColor = match($trip->status->value) { 'confirmed', 'completed' => 'green', 'cancelled' => 'red', 'departed' => 'blue', default => 'amber' };
                                $eligible = $unassigned->filter(fn($item) => $item['direction'] === $trip->direction);
                                $isCancelled = $trip->status->value === 'cancelled';
                            @endphp
                            <tr class="pm-shuttle-summary-row {{ $isCancelled ? 'is-cancelled' : '' }}" :class="{ 'is-expanded': openTrip === {{ $trip->id }} }">
                                <td data-label="{{ __('Orario') }}"><time class="pm-shuttle-table-time" datetime="{{ $trip->scheduled_at->toIso8601String() }}">{{ $trip->scheduled_at->format('H:i') }}</time></td>
                                <td data-label="{{ __('Direzione') }}"><span class="pm-shuttle-direction-label">{{ $trip->direction->label() }}</span></td>
                                <td data-label="{{ __('Navetta') }}"><span class="pm-td-main">{{ $trip->vehicle?->name ?? __('Navetta da assegnare') }}</span></td>
                                <td data-label="{{ __('Prenotazioni') }}">
                                    <div class="pm-shuttle-table-groups">
                                        @forelse($trip->assignments as $assignment)
                                            <span><strong>{{ $assignment->reservation->customer_name }}</strong><small>{{ $assignment->passengers }} {{ __('pax') }} · {{ $assignment->reservation->flight_reference ?: __('volo non indicato') }}</small></span>
                                        @empty
                                            <span class="pm-shuttle-empty">{{ __('Nessun gruppo assegnato.') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td data-label="{{ __('Posti occupati') }}"><strong class="pm-mono">{{ $trip->assigned_passengers }} / {{ $trip->capacity }}</strong></td>
                                <td data-label="{{ __('Stato') }}"><span class="pm-badge {{ $tripStatusColor }}">{{ $trip->status->label() }}</span></td>
                                <td data-label="{{ __('Azioni') }}" class="pm-table-action">
                                    @if(!$isCancelled)
                                        <button
                                            type="button"
                                            class="pm-btn pm-btn-secondary pm-btn-sm"
                                            @click="openTrip = openTrip === {{ $trip->id }} ? null : {{ $trip->id }}"
                                            :aria-expanded="openTrip === {{ $trip->id }}"
                                            aria-controls="shuttle-trip-{{ $trip->id }}"
                                        >{{ __('Apri') }}</button>
                                    @else
                                        <span aria-hidden="true">—</span>
                                    @endif
                                </td>
                            </tr>

                            @if(!$isCancelled)
                                <tr id="shuttle-trip-{{ $trip->id }}" class="pm-shuttle-detail-row" x-show="openTrip === {{ $trip->id }}" x-cloak>
                                    <td colspan="7">
                                        <div class="pm-shuttle-trip-panel">
                                            <section class="pm-shuttle-panel-section" aria-labelledby="shuttle-passengers-{{ $trip->id }}">
                                                <div>
                                                    <h3 id="shuttle-passengers-{{ $trip->id }}">{{ __('Passeggeri navetta') }}</h3>
                                                    <p>{{ $trip->assigned_passengers }} / {{ $trip->capacity }} {{ __('posti') }}</p>
                                                </div>
                                                <div class="pm-shuttle-passengers">
                                                    @forelse($trip->assignments as $assignment)
                                                        <div>
                                                            <div><strong>{{ $assignment->reservation->customer_name }}</strong><span>{{ $assignment->passengers }} {{ __('pax') }} · {{ $assignment->reservation->flight_reference ?: __('volo non indicato') }}</span></div>
                                                            <form method="POST" action="{{ route('shuttles.assignments.destroy', $assignment) }}">@csrf @method('DELETE')<button type="submit" class="pm-text-button">{{ __('Rimuovi') }}</button></form>
                                                        </div>
                                                    @empty<div class="pm-shuttle-empty">{{ __('Nessun gruppo assegnato.') }}</div>@endforelse
                                                </div>

                                                @if($eligible->isNotEmpty() && $trip->remaining_seats > 0)
                                                    <form method="POST" action="{{ route('shuttles.assignments.store', $trip) }}" class="pm-form pm-shuttle-assignment-form">
                                                        @csrf
                                                        <h3>{{ __('Assegna gruppo') }}</h3>
                                                        <div class="pm-form-group"><label class="pm-label">{{ __('Prenotazione') }}</label><select name="reservation_id" class="pm-select">@foreach($eligible as $item)<option value="{{ $item['reservation']->id }}">{{ $item['time']->format('H:i') }} · {{ $item['reservation']->customer_name }} · {{ $item['remaining'] }} {{ __('pax') }}</option>@endforeach</select></div>
                                                        <div class="pm-form-group"><label class="pm-label">{{ __('Passeggeri da assegnare') }}</label><input name="passengers" type="number" min="1" max="{{ $trip->remaining_seats }}" value="1" class="pm-input" required></div>
                                                        <button class="pm-btn pm-btn-secondary" type="submit">{{ __('Assegna') }}</button>
                                                    </form>
                                                @endif
                                            </section>

                                            <section class="pm-shuttle-panel-section" aria-labelledby="shuttle-edit-{{ $trip->id }}">
                                                <h3 id="shuttle-edit-{{ $trip->id }}">{{ __('Modifica viaggio') }}</h3>
                                                <form method="POST" action="{{ route('shuttles.trips.update', $trip) }}" class="pm-form pm-mt-16">
                                                    @csrf @method('PUT')
                                                    <div class="pm-form-grid-2">
                                                        <div class="pm-form-group"><label class="pm-label">{{ __('Orario') }}</label><input name="scheduled_at" type="datetime-local" value="{{ $trip->scheduled_at->format('Y-m-d\TH:i') }}" class="pm-input" required></div>
                                                        <div class="pm-form-group"><label class="pm-label">{{ __('Navetta') }}</label><select name="shuttle_vehicle_id" class="pm-select"><option value="">{{ __('Da assegnare') }}</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected($trip->shuttle_vehicle_id === $vehicle->id)>{{ $vehicle->name }} · {{ $vehicle->seats }} {{ __('posti') }}</option>@endforeach</select></div>
                                                        <div class="pm-form-group"><label class="pm-label">{{ __('Capienza') }}</label><input name="capacity" type="number" min="1" value="{{ $trip->capacity }}" class="pm-input" required></div>
                                                        <div class="pm-form-group"><label class="pm-label">{{ __('Stato') }}</label><select name="status" class="pm-select">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($trip->status === $status)>{{ $status->label() }}</option>@endforeach</select></div>
                                                        <div class="pm-form-group pm-form-span-2"><label class="pm-label">{{ __('Note') }}</label><textarea name="notes" class="pm-textarea">{{ $trip->notes }}</textarea></div>
                                                    </div>
                                                    <div class="pm-form-actions"><button class="pm-btn pm-btn-primary" type="submit">{{ __('Salva modifiche') }}</button></div>
                                                </form>
                                                <form method="POST" action="{{ route('shuttles.trips.cancel', $trip) }}" class="pm-shuttle-cancel" onsubmit="return confirm(@js(__('Annullare questo viaggio?')))" >@csrf<button class="pm-text-button is-danger" type="submit">{{ __('Annulla viaggio') }}</button></form>
                                            </section>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr class="pm-responsive-empty"><td colspan="7"><strong>{{ __('Nessun viaggio pianificato.') }}</strong><span class="pm-td-sub">{{ __('Usa “Calcola viaggi” oppure crea un viaggio manualmente.') }}</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

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
