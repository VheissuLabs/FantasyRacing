<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventConstructorResult extends Model
{
    protected $fillable = [
        'event_id',
        'constructor_id',
        'fantasy_points',
        'fantasy_breakdown',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    protected function casts(): array
    {
        return [
            'fantasy_points' => 'decimal:2',
            'fantasy_breakdown' => 'array',
        ];
    }
}
