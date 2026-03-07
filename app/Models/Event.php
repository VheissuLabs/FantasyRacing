<?php

namespace App\Models;

use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'track_id',
        'name',
        'type',
        'scheduled_at',
        'locked_at',
        'status',
        'sort_order',
        'round',
        'openf1_session_key',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(EventResult::class);
    }

    public function pitstops(): HasMany
    {
        return $this->hasMany(EventPitstop::class);
    }

    public function fantasyPoints(): HasMany
    {
        return $this->hasMany(FantasyEventPoint::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null && $this->locked_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
