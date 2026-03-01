<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DraftSession extends Model
{
    protected $fillable = [
        'league_id',
        'type',
        'pick_time_limit_seconds',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'notified_at',
        'current_pick_number',
        'total_picks',
        'paused_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function pauser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DraftOrder::class)->orderBy('pick_number');
    }

    public function picks(): HasMany
    {
        return $this->hasMany(DraftPick::class)->orderBy('pick_number');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function currentOrder(): ?DraftOrder
    {
        return $this->orders()->where('pick_number', $this->current_pick_number)->first();
    }
}
