<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TradeItem extends Model
{
    protected $fillable = [
        'trade_id',
        'from_team_id',
        'to_team_id',
        'entity_type',
        'entity_id',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'to_team_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    public function isFromFreeAgent(): bool
    {
        return $this->from_team_id === null;
    }

    public function isToFreeAgent(): bool
    {
        return $this->to_team_id === null;
    }
}
