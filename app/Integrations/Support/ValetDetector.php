<?php

namespace App\Integrations\Support;

class ValetDetector
{
    public static function fromPayload(string $platform, array $payload, array $services = []): ?bool
    {
        return match ($platform) {
            'parkos' => self::fromLabel($payload['parking_type'] ?? null),
            'parking-my-car' => self::fromLabel($payload['carvalet'] ?? null),
            'vologio' => self::fromRooshService($payload, $services),
            default => null,
        };
    }

    public static function fromLabel(mixed $value): ?bool
    {
        if (! is_string($value)) {
            return null;
        }

        /** Unknown values stay unclassified; numbers and notes are not evidence of a valet service. */
        return match (strtolower(trim(preg_replace('/\s+/', ' ', $value)))) {
            'valet', 'car valet', 'carvalet', 'parcheggio + car valet' => true,
            'shuttle', 'parcheggio' => false,
            default => null,
        };
    }

    public static function rooshServices(array $catalog): array
    {
        if (! isset($catalog['serviceLocations']) || ! is_array($catalog['serviceLocations'])) {
            throw new \UnexpectedValueException('Catalogo servizi Roosh non riconosciuto.');
        }

        $services = [];
        foreach ($catalog['serviceLocations'] as $location) {
            if (! is_array($location) || ! is_scalar($location['id'] ?? null)
                || trim((string) $location['id']) === '' || ! is_array($location['services'] ?? [])) {
                continue;
            }
            foreach (($location['services'] ?? []) as $service) {
                if (! is_array($service) || ! is_scalar($service['id'] ?? null) || trim((string) $service['id']) === '') {
                    continue;
                }
                $id = (string) $service['id'];
                $entry = [
                    'location_id' => (string) $location['id'],
                    'is_valet' => self::fromLabel($service['parking_type_name'] ?? $location['parking_type_name'] ?? null),
                ];
                /** Disabled services can still identify historical reservations. Conflicting IDs are never inferred. */
                $services[$id] = array_key_exists($id, $services) && $services[$id] !== $entry
                    ? ['location_id' => null, 'is_valet' => null]
                    : $entry;
            }
        }

        return $services;
    }

    private static function fromRooshService(array $payload, array $services): ?bool
    {
        if (! is_scalar($payload['service_id'] ?? null)) {
            return null;
        }
        $service = $services[(string) $payload['service_id']] ?? null;
        $locationId = $payload['service_location_id'] ?? $payload['service_location']['id'] ?? $payload['serviceLocation']['id'] ?? null;
        if (! $service || ($locationId !== null && (! is_scalar($locationId) || (string) $locationId !== $service['location_id']))) {
            return null;
        }

        return $service['is_valet'];
    }
}
