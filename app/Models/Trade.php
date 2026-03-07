<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'initiator_team_id',
        'receiver_team_id',
        'status',
        'initiated_at',
        'resolved_at',
        'notes',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function initiatorTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'initiator_team_id');
    }

    public function receiverTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class, 'receiver_team_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TradeItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    protected function casts(): array
    {
        return [
            'initiated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
