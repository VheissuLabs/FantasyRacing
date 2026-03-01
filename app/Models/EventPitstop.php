<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPitstop extends Model
{
    protected $fillable = [
        'event_id',
        'constructor_id',
        'driver_id',
        'stop_lap',
        'stop_time_seconds',
        'is_fastest_of_event',
        'data_source',
    ];

    protected function casts(): array
    {
        return [
            'is_fastest_of_event' => 'boolean',
            'stop_time_seconds' => 'decimal:3',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
