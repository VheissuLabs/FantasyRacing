<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSeasonStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'season_id',
        'constructor_id',
        'races_entered',
        'races_classified',
        'wins',
        'podiums',
        'poles',
        'fastest_laps',
        'dnfs',
        'sprint_wins',
        'sprint_podiums',
        'sprint_fastest_laps',
        'points_total',
        'best_finish',
        'championship_position',
        'fantasy_points_total',
        'fantasy_ownership_pct',
        'last_computed_at',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    protected function casts(): array
    {
        return [
            'points_total' => 'decimal:2',
            'fantasy_points_total' => 'decimal:2',
            'fantasy_ownership_pct' => 'decimal:2',
            'last_computed_at' => 'datetime',
        ];
    }
}
