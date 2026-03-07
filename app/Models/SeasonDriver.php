<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonDriver extends Model
{
    protected $fillable = [
        'season_id',
        'driver_id',
        'constructor_id',
        'number',
        'effective_from',
        'effective_to',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }

    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
