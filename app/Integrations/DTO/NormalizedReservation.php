<?php

namespace App\Integrations\DTO;

use Carbon\Carbon;

class NormalizedReservation
{
    public function __construct(
        public readonly string $external_id,
        public readonly string $external_product_ref,
        public readonly ?string $external_product_name,
        public readonly string $customer_name,
        public readonly ?string $customer_email,
        public readonly ?string $customer_phone,
        public readonly ?string $license_plate,
        public readonly Carbon $starts_at,
        public readonly Carbon $ends_at,
        public readonly int $spots = 1,
        public readonly ?float $price = null,
        public readonly ?string $currency = null,
        public readonly ?string $notes = null,
        public readonly array $raw_data = [],
        public readonly ?string $status = null,
        public readonly ?string $flight_reference = null,
        public readonly ?int $passengers_count = null,
        public readonly ?Carbon $platform_created_at = null,
        public readonly ?Carbon $platform_updated_at = null,
        public readonly ?Carbon $platform_cancelled_at = null
    ) {}
}
