<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsScheme extends Model
{
    protected $fillable = [
        'franchise_id',
        'event_type',
        'position',
        'points',
    ];

    public static function getPointsForPosition(string $eventType, int $position, int $franchiseId): float
    {
        return static::where('franchise_id', $franchiseId)
            ->where('event_type', $eventType)
            ->where('position', $position)
            ->first()?->points ?? 0;
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
        ];
    }
}
