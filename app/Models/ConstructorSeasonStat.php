<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructorSeasonStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'constructor_id',
        'season_id',
        'races_entered',
        'wins',
        'podiums',
        'one_twos',
        'poles',
        'fastest_laps',
        'sprint_wins',
        'sprint_podiums',
        'sprint_one_twos',
        'points_total',
        'best_finish',
        'championship_position',
        'fantasy_points_total',
        'fantasy_ownership_pct',
        'last_computed_at',
    ];

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
