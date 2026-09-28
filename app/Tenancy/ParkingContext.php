<?php

namespace App\Tenancy;

use App\Models\Management;
use App\Models\Parking;
use Closure;

/** Request/job-local context. Never store tenant state in global configuration. */
class ParkingContext
{
    public bool $restricted = false;

    public ?Parking $parking = null;

    public ?Management $management = null;

    public function select(?Parking $parking, ?Management $management = null): void
    {
        $this->restricted = true;
        $this->parking = $parking;
        $this->management = $management ?? $parking?->management;
    }

    public function clear(): void
    {
        $this->restricted = false;
        $this->parking = null;
        $this->management = null;
    }

    public function run(Parking $parking, Closure $callback): mixed
    {
        if ($this->restricted) {
            abort_unless($this->management?->id === (int) $parking->management_id, 404);
            abort_if($this->parking && $this->parking->id !== $parking->id, 404);
        }
        $previous = [$this->restricted, $this->parking, $this->management];
        try {
            $this->select($parking);

            return $callback();
        } finally {
            [$this->restricted, $this->parking, $this->management] = $previous;
        }
    }
}
