<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'name',
        'year',
        'is_active',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('sort_order');
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }

    public function seasonConstructors(): HasMany
    {
        return $this->hasMany(SeasonConstructor::class);
    }

    public function seasonDrivers(): HasMany
    {
        return $this->hasMany(SeasonDriver::class);
    }

    public function driverSeasonStats(): HasMany
    {
        return $this->hasMany(DriverSeasonStat::class);
    }

    public function constructorSeasonStats(): HasMany
    {
        return $this->hasMany(ConstructorSeasonStat::class);
    }

    public function fantasyTeams(): HasManyThrough
    {
        return $this->hasManyThrough(FantasyTeam::class, League::class);
    }

    public function activeEvents(): HasMany
    {
        return $this->hasMany(Event::class)->where('status', '!=', 'cancelled');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
