<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="pm-page-title">{{ __('Modifica prenotazione') }}</h1>
            <div class="pm-page-subtitle">{{ $reservation->customer_name }}</div>
        </div>
        <a href="{{ route('reservations.index') }}" class="pm-btn pm-btn-secondary">
            {{ __('Torna alla lista') }}
        </a>
    </x-slot>

    <x-flash-message />

    <div class="pm-card pm-animate" style="max-width:720px">
        <form method="POST" action="{{ route('reservations.update', $reservation) }}" class="pm-form">
            @csrf
            @method('PUT')

            <div class="pm-form-group" style="margin-bottom:24px;">
                <label class="pm-label">{{ __('Categoria / Tipologia posto') }}</label>
                <select name="parking_product_id" class="pm-select">
                    <option value="">{{ __('Nessuna categoria') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                            {{ old('parking_product_id', $reservation->parking_product_id) == $product->id ? 'selected' : '' }}>
                            {{ __($product->name) }} (€ {{ number_format($product->price, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pm-form-grid-3">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Nome cliente') }}</label>
                    <input type="text" name="customer_name"
                           value="{{ old('customer_name', $reservation->customer_name) }}"
                           required class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label">{{ __('Email cliente') }}</label>
                    <input type="email" name="customer_email"
                           value="{{ old('customer_email', $reservation->customer_email) }}"
                           class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label">{{ __('Telefono') }}</label>
                    <input type="text" name="customer_phone"
                           value="{{ old('customer_phone', $reservation->customer_phone) }}"
                           class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label">{{ __('Targa') }}</label>
                    <input type="text" name="license_plate"
                           value="{{ old('license_plate', $reservation->license_plate) }}"
                           class="pm-input" style="text-transform: uppercase;" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label">{{ __('Riferimento volo') }}</label>
                    <input type="text" name="flight_reference"
                           value="{{ old('flight_reference', $reservation->flight_reference) }}"
                           class="pm-input" placeholder="Es. AZ1602" style="text-transform: uppercase;" />
                </div>
            </div>

            <div class="pm-form-grid-2">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Data arrivo') }}</label>
                    <input type="datetime-local" name="starts_at" required
                           value="{{ old('starts_at', $reservation->starts_at->format('Y-m-d\TH:i')) }}"
                           class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Data partenza') }}</label>
                    <input type="datetime-local" name="ends_at" required
                           value="{{ old('ends_at', $reservation->ends_at->format('Y-m-d\TH:i')) }}"
                           class="pm-input" />
                </div>
            </div>

            <div class="pm-reservation-grid-4">
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Posti') }}</label>
                    <input type="number" name="spots" min="1" required
                           value="{{ old('spots', $reservation->spots) }}"
                           class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Passeggeri navetta') }}</label>
                    <input type="number" name="passengers_count" min="1" max="9" required
                           value="{{ old('passengers_count', $reservation->passengers_count ?? 1) }}"
                           class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label">{{ __('Prezzo') }} (€)</label>
                    <input type="number" name="price" min="0" step="0.01"
                           value="{{ old('price', $reservation->price) }}"
                           class="pm-input" />
                </div>
                <div class="pm-form-group">
                    <label class="pm-label pm-label-required">{{ __('Stato') }}</label>
                    <select name="status" required class="pm-select">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}"
                                {{ old('status', $reservation->status->value) === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="pm-form-group">
                <label class="pm-label">{{ __('Note') }}</label>
                <textarea name="notes" class="pm-textarea">{{ old('notes', $reservation->notes) }}</textarea>
            </div>

            <div class="pm-form-actions">
                <button type="submit" class="pm-btn pm-btn-primary">{{ __('Salva modifiche') }}</button>
                <a href="{{ route('reservations.index') }}" class="pm-btn pm-btn-secondary">{{ __('Annulla') }}</a>
            </div>

        </form>
    </div>

</x-app-layout>
