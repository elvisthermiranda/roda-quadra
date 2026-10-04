<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\RosterChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterChange>
 */
class RosterChangeFactory extends Factory
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
            'type' => 'departure',
            'outgoing_participant_id' => fn (array $attributes): int => Participant::factory()->create([
                'meeting_id' => $attributes['meeting_id'],
                'status' => 'left',
                'left_at' => now(),
            ])->id,
        ];
    }
}
