<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonConstructor extends Model
{
    protected $fillable = [
        'season_id',
        'constructor_id',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function constructor(): BelongsTo
    {
        return $this->belongsTo(Constructor::class);
    }
}
