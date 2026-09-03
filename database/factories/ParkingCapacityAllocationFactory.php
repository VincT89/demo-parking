<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Parking;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ParkingCapacityAllocation>
 */
class ParkingCapacityAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parking_id' => Parking::factory(),
            'allocation_type' => $this->faker->randomElement(['rentcar', 'internal_use', 'partner', 'maintenance', 'other']),
            'spots' => $this->faker->numberBetween(1, 10),
            'starts_at' => now(),
            'ends_at' => now()->addDays(5),
            'notes' => $this->faker->sentence,
            'is_active' => true,
        ];
    }
}
