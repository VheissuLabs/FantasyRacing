<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FreeAgentPool extends Model
{
    protected $table = 'free_agent_pool';

    protected $fillable = [
        'league_id',
        'entity_type',
        'entity_id',
        'added_at',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
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

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
        ];
    }
}
