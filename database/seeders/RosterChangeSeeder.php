<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Services\SubstitutionService;
use App\Services\TeamFormationService;
use Illuminate\Database\Seeder;

class RosterChangeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(TeamFormationService $formation, SubstitutionService $substitutions): void
    {
        $meeting = Meeting::factory()->create();

        for ($number = 1; $number <= 9; $number++) {
            $formation->arrive($meeting, 'Jogador '.$number, $number % 2 === 0 ? 'female' : 'male', 3);
        }

        $substitutions->depart($meeting, $meeting->participants()->firstOrFail()->id);
    }
}
