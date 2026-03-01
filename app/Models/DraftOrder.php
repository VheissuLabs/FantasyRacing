<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftOrder extends Model
{
    protected $fillable = [
        'draft_session_id',
        'pick_number',
        'round',
        'round_pick',
        'fantasy_team_id',
        'entity_type_restriction',
        'status',
    ];

    public function draftSession(): BelongsTo
    {
        return $this->belongsTo(DraftSession::class);
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function pick(): ?DraftPick
    {
        return $this->hasOne(DraftPick::class)->first();
    }

    public function isConstructorRound(): bool
    {
        return $this->entity_type_restriction === 'constructor';
    }

    public function isDriverRound(): bool
    {
        return $this->entity_type_restriction === 'driver';
    }
}
