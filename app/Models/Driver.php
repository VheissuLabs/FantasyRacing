<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'country_id',
        'name',
        'slug',
        'photo_path',
        'is_active',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function seasonDrivers(): HasMany
    {
        return $this->hasMany(SeasonDriver::class);
    }

    public function driverSeasonStats(): HasMany
    {
        return $this->hasMany(DriverSeasonStat::class);
    }

    public function eventResults(): HasMany
    {
        return $this->hasMany(EventResult::class);
    }

    public function eventPitstops(): HasMany
    {
        return $this->hasMany(EventPitstop::class);
    }

    public function currentConstructor(int $seasonId): ?Constructor
    {
        return $this->seasonDrivers()
            ->where('season_id', $seasonId)
            ->whereNull('effective_to')
            ->first()?->constructor;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
