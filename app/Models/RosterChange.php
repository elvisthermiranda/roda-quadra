<?php

namespace App\Models;

use Database\Factories\RosterChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterChange extends Model
{
    /** @use HasFactory<RosterChangeFactory> */
    use HasFactory;

    protected $fillable = ['meeting_id', 'team_id', 'source_team_id', 'match_id', 'outgoing_participant_id', 'incoming_participant_id', 'type'];

    /** @return BelongsTo<Participant, $this> */
    public function outgoingParticipant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'outgoing_participant_id');
    }

    /** @return BelongsTo<Participant, $this> */
    public function incomingParticipant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'incoming_participant_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function sourceTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'source_team_id');
    }
}
