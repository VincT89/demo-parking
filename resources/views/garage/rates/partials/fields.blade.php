@php
    $editing = isset($rate) && $rate;
@endphp

<div class="pm-form-grid-3">
    <div class="pm-form-group">
        <label class="pm-label pm-label-required">{{ __('Nome tariffa') }}</label>
        <input name="name" value="{{ old('name', $editing ? $rate->name : '') }}" class="pm-input" required>
    </div>
    <div class="pm-form-group">
        <label class="pm-label pm-label-required">{{ __('Categoria parcheggio') }}</label>
        <select name="parking_product_id" class="pm-select" required>
            @foreach ($parking->products as $product)
                <option value="{{ $product->id }}" @selected((int) old('parking_product_id', $editing ? $rate->parking_product_id : 0) === $product->id)>{{ __($product->name) }}</option>
            @endforeach
        </select>
    </div>
    <div class="pm-form-group">
        <label class="pm-label pm-label-required">{{ __('Tipo tariffa') }}</label>
        <select name="kind" class="pm-select" required>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $editing ? $rate->kind->value : '') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="pm-form-group">
        <label class="pm-label pm-label-required">{{ __('Unità di calcolo') }}</label>
        <select name="billing_unit" class="pm-select" required>
            @foreach ($billingUnits as $unit)
                <option value="{{ $unit->value }}" @selected(old('billing_unit', $editing ? $rate->billing_unit->value : '') === $unit->value)>{{ $unit->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="pm-form-group">
        <label class="pm-label pm-label-required">{{ __('Prezzo') }}</label>
        <div class="pm-input-suffix"><input name="price" type="number" min="0" step="0.01" value="{{ old('price', $editing ? $rate->price : '') }}" class="pm-input" required><span>€</span></div>
    </div>
    <div class="pm-form-group">
        <label class="pm-label pm-label-required">{{ __('Valuta') }}</label>
        <input name="currency" value="{{ old('currency', $editing ? $rate->currency : 'EUR') }}" maxlength="3" class="pm-input" required>
    </div>
    <div class="pm-form-group">
        <label class="pm-label">{{ __('Tolleranza in minuti') }}</label>
        <input name="grace_minutes" type="number" min="0" max="1440" value="{{ old('grace_minutes', $editing ? $rate->grace_minutes : 0) }}" class="pm-input" required>
    </div>
    <div class="pm-form-group">
        <label class="pm-label">{{ __('Unità minime') }}</label>
        <input name="minimum_units" type="number" min="1" value="{{ old('minimum_units', $editing ? $rate->minimum_units : 1) }}" class="pm-input" required>
    </div>
    <div class="pm-form-group">
        <label class="pm-label">{{ __('Ordine') }}</label>
        <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $editing ? $rate->sort_order : 0) }}" class="pm-input">
    </div>
    <div class="pm-form-group">
        <label class="pm-label">{{ __('Valida dal') }}</label>
        <input name="valid_from" type="date" value="{{ old('valid_from', $editing ? $rate->valid_from?->format('Y-m-d') : '') }}" class="pm-input">
    </div>
    <div class="pm-form-group">
        <label class="pm-label">{{ __('Valida fino al') }}</label>
        <input name="valid_until" type="date" value="{{ old('valid_until', $editing ? $rate->valid_until?->format('Y-m-d') : '') }}" class="pm-input">
    </div>
    <label class="pm-switch-row">
        <input type="checkbox" name="is_active" value="1" class="pm-checkbox" @checked(old('is_active', $editing ? $rate->is_active : true))>
        <span><strong>{{ __('Tariffa attiva') }}</strong><small>{{ __('Disponibile nei nuovi inserimenti.') }}</small></span>
    </label>
</div>
