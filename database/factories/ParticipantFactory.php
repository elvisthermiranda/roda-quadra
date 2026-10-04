<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
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
            'player_id' => Player::factory(),
            'arrival_order' => fake()->unique()->numberBetween(1, 100000),
            'status' => 'present',
        ];
    }
}
