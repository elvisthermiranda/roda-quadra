<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\VolleyballMatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, array{id: int, name: string}> $home_roster
 * @property array<int, array{id: int, name: string}> $away_roster
 * @property array<int, array{id: int, status: string, queue_position: int|null, consecutive_games: int}>|null $queue_snapshot
 * @property CarbonInterface|null $timer_started_at
 * @property int $timer_elapsed_seconds
 */
class VolleyballMatch extends Model
{
    /** @use HasFactory<VolleyballMatchFactory> */
    use HasFactory;

    protected $fillable = ['meeting_id', 'home_team_id', 'away_team_id', 'winner_team_id', 'sequence', 'home_score', 'away_score', 'status', 'home_roster', 'away_roster', 'queue_snapshot', 'confirmed_at', 'incomplete_confirmed', 'score_started_at', 'target_score', 'duration_minutes', 'timer_started_at', 'timer_elapsed_seconds'];

    protected function casts(): array
    {
        return ['home_roster' => 'array', 'away_roster' => 'array', 'queue_snapshot' => 'array', 'confirmed_at' => 'datetime', 'incomplete_confirmed' => 'boolean', 'score_started_at' => 'datetime', 'target_score' => 'integer', 'duration_minutes' => 'integer', 'timer_started_at' => 'datetime', 'timer_elapsed_seconds' => 'integer'];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
