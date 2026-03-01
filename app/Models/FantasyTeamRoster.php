<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FantasyTeamRoster extends Model
{
    protected $table = 'fantasy_team_roster';

    protected $fillable = [
        'fantasy_team_id',
        'entity_type',
        'entity_id',
        'in_seat',
        'acquired_at',
    ];

    protected function casts(): array
    {
        return [
            'in_seat' => 'boolean',
            'acquired_at' => 'datetime',
        ];
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    public function isDriver(): bool
    {
        return $this->entity_type === 'driver';
    }

    public function isConstructor(): bool
    {
        return $this->entity_type === 'constructor';
    }
}
