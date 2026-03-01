<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusPointsScheme extends Model
{
    protected $fillable = [
        'franchise_id',
        'event_type',
        'bonus_key',
        'applies_to',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
        ];
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public static function getBonusPoints(
        string $eventType,
        string $bonusKey,
        string $appliesTo,
        int $franchiseId
    ): float {
        return static::where('franchise_id', $franchiseId)
            ->where('event_type', $eventType)
            ->where('bonus_key', $bonusKey)
            ->where('applies_to', $appliesTo)
            ->first()?->points ?? 0;
    }
}
