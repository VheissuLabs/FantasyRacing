<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterSnapshot extends Model
{
    protected $fillable = [
        'event_id',
        'fantasy_team_id',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }
}
