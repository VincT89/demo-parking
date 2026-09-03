@props(['variant' => 'full'])

<span
    {{ $attributes->class(['pm-brand-logo', 'pm-brand-logo--'.$variant]) }}
    role="img"
    aria-label="{{ config('demo.brand_name', 'Sodano Consulting') }}"
>
    <img src="{{ asset('img/sodano-consulting-source.png') }}" alt="" aria-hidden="true">
</span>
