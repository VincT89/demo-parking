@props(['locale'])

<svg {{ $attributes->class(['pm-locale-flag']) }} viewBox="0 0 24 16" aria-hidden="true" focusable="false">
    @switch($locale)
        @case('it')
            <rect width="8" height="16" x="0" fill="#009246" />
            <rect width="8" height="16" x="8" fill="#ffffff" />
            <rect width="8" height="16" x="16" fill="#ce2b37" />
            @break
        @case('en_GB')
            <rect width="24" height="16" fill="#012169" />
            <path d="M0 0L24 16M24 0L0 16" stroke="#ffffff" stroke-width="4" />
            <path d="M0 0L24 16M24 0L0 16" stroke="#c8102e" stroke-width="1.5" />
            <path d="M12 0V16M0 8H24" stroke="#ffffff" stroke-width="5" />
            <path d="M12 0V16M0 8H24" stroke="#c8102e" stroke-width="2.5" />
            @break
        @case('nl')
            <rect width="24" height="5.34" y="0" fill="#ae1c28" />
            <rect width="24" height="5.34" y="5.33" fill="#ffffff" />
            <rect width="24" height="5.34" y="10.66" fill="#21468b" />
            @break
    @endswitch
</svg>
