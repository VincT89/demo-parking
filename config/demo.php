<?php

return [
    'enabled' => (bool) env('DEMO_MODE', false),
    'brand_name' => env('DEMO_BRAND_NAME', 'Sodano Consulting'),
    'brand_url' => env('DEMO_BRAND_URL', 'https://sodanoconsulting.it'),
    'days_before' => (int) env('DEMO_DAYS_BEFORE', 7),
    'days_after' => (int) env('DEMO_DAYS_AFTER', 23),
    'api_latency_ms' => (int) env('DEMO_API_LATENCY_MS', 650),
    'reservations_per_day' => (int) env('DEMO_RESERVATIONS_PER_DAY', 3),
];
