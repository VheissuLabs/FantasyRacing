<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'driver_id',
        'constructor_id',
        'finish_position',
        'grid_position',
        'status',
        'fastest_lap',
        'driver_of_the_day',
        'overtakes_made',
        'q1_time',
        'q2_time',
        'q3_time',
        'teammate_outqualified',
        'points_eligible',
        'fantasy_points',
        'fantasy_breakdown',
        'fia_points',
        'data_source',
        'notes',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    public function isClassified(): bool
    {
        return $this->status === 'classified';
    }

    public function hasPenalty(): bool
    {
        return in_array($this->status, ['dnf', 'dns', 'dsq', 'not_classified']);
    }

    protected function casts(): array
    {
        return [
            'fastest_lap' => 'boolean',
            'driver_of_the_day' => 'boolean',
            'points_eligible' => 'boolean',
            'fantasy_points' => 'decimal:2',
            'fantasy_breakdown' => 'array',
            'fia_points' => 'decimal:2',
            'q1_time' => 'datetime:H:i:s',
            'q2_time' => 'datetime:H:i:s',
            'q3_time' => 'datetime:H:i:s',
        ];
    }
}
