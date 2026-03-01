<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DraftPick extends Model
{
    protected $fillable = [
        'draft_session_id',
        'draft_order_id',
        'fantasy_team_id',
        'pick_number',
        'entity_type',
        'entity_id',
        'is_auto_pick',
        'picked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_auto_pick' => 'boolean',
            'picked_at' => 'datetime',
        ];
    }

    public function draftSession(): BelongsTo
    {
        return $this->belongsTo(DraftSession::class);
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
    }

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
