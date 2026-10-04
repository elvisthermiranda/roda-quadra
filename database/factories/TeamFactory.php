<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'name' => 'Time '.fake()->unique()->numberBetween(1, 100000),
            'status' => 'waiting',
        ];
    }
}
