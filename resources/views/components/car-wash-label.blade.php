@props(['type'])

@if ($type)
    <span class="pm-badge blue pm-car-wash-label">{{ $type->label() }}</span>
@endif
