<?php

namespace App\Models;

use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'played_on', 'sport', 'team_size', 'status', 'gender_weight', 'skill_weight', 'ranking_tiebreaker', 'target_score', 'duration_minutes', 'team_mode'];

    protected $hidden = ['display_token'];

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting): void {
            $meeting->display_token ??= Str::random(40);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['played_on' => 'date', 'gender_weight' => 'integer', 'skill_weight' => 'integer', 'target_score' => 'integer', 'duration_minutes' => 'integer'];
    }

    /** @return HasMany<Participant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<VolleyballMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(VolleyballMatch::class);
    }

    /** @return HasMany<RosterChange, $this> */
    public function rosterChanges(): HasMany
    {
        return $this->hasMany(RosterChange::class);
    }
}
