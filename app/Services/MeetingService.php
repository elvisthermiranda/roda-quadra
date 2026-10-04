<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    public function create(User $user, int $teamSize, string $sport = 'volleyball'): Meeting
    {
        return DB::transaction(function () use ($user, $teamSize, $sport): Meeting {
            if (! in_array($sport, ['volleyball', 'futsal', 'football'], true)) {
                throw ValidationException::withMessages(['sport' => 'Escolha uma modalidade válida.']);
            }

            if ($teamSize < 2 || $teamSize > 11) {
                throw ValidationException::withMessages(['teamSize' => 'Escolha entre 2 e 11 jogadores por time.']);
            }

            if ($user->meetings()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['meeting' => 'Encerre o encontro ativo antes de iniciar outro.']);
            }

            return $user->meetings()->create([
                'played_on' => today(),
                'sport' => $sport,
                'team_size' => $teamSize,
                'status' => 'active',
                'gender_weight' => config('volleyball.formation.gender_weight', 100),
                'skill_weight' => config('volleyball.formation.skill_weight', 1),
                'ranking_tiebreaker' => config('volleyball.ranking_tiebreaker', 'win_rate'),
                'target_score' => config('volleyball.target_score', 15),
                'team_mode' => config('volleyball.team_mode', 'dynamic'),
            ]);
        });
    }

    public function updateSettings(Meeting $meeting, int $genderWeight, int $skillWeight, string $rankingTiebreaker, int $targetScore = 15, ?int $durationMinutes = null, string $teamMode = 'dynamic'): void
    {
        DB::transaction(function () use ($meeting, $genderWeight, $skillWeight, $rankingTiebreaker, $targetScore, $durationMinutes, $teamMode): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);

            if ($meeting->status !== 'active') {
                throw ValidationException::withMessages(['meeting' => 'Este encontro já foi encerrado.']);
            }

            if ($genderWeight < 0 || $genderWeight > 100 || $skillWeight < 0 || $skillWeight > 100 || ($genderWeight === 0 && $skillWeight === 0)) {
                throw ValidationException::withMessages(['settings' => 'Defina pesos entre 0 e 100, com pelo menos um peso maior que zero.']);
            }

            if (! in_array($rankingTiebreaker, ['win_rate', 'point_balance'], true)) {
                throw ValidationException::withMessages(['settings' => 'Critério de desempate inválido.']);
            }

            if ($targetScore < 5 || $targetScore > 30) {
                throw ValidationException::withMessages(['settings' => 'A meta deve ficar entre 5 e 30 pontos.']);
            }

            if ($durationMinutes !== null && ($durationMinutes < 1 || $durationMinutes > 120)) {
                throw ValidationException::withMessages(['settings' => 'A duração deve ficar entre 1 e 120 minutos.']);
            }

            if (! in_array($teamMode, ['fixed', 'dynamic'], true)) {
                throw ValidationException::withMessages(['settings' => 'Modo de times inválido.']);
            }

            $meeting->update([
                'gender_weight' => $genderWeight,
                'skill_weight' => $skillWeight,
                'ranking_tiebreaker' => $rankingTiebreaker,
                'target_score' => $targetScore,
                'duration_minutes' => $durationMinutes,
                'team_mode' => $teamMode,
            ]);
        });
    }

    public function close(Meeting $meeting): void
    {
        DB::transaction(function () use ($meeting): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);

            if ($meeting->status !== 'active') {
                throw ValidationException::withMessages(['meeting' => 'Este encontro já foi encerrado.']);
            }

            $current = $meeting->matches()->where('status', 'playing')->lockForUpdate()->first();

            $current?->update(['status' => 'cancelled']);
            $meeting->update(['status' => 'completed']);
        });
    }
}
