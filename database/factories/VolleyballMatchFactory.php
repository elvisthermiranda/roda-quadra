<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Team;
use App\Models\VolleyballMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolleyballMatch>
 */
class VolleyballMatchFactory extends Factory
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
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 100000),
            'home_roster' => [],
            'away_roster' => [],
        ];
    }
}
