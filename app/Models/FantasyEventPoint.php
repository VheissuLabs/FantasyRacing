<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FantasyEventPoint extends Model
{
    protected $fillable = [
        'fantasy_team_id',
        'event_id',
        'entity_type',
        'entity_id',
        'points',
        'breakdown',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
