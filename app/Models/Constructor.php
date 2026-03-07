<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Constructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'country_id',
        'name',
        'slug',
        'logo_path',
        'team_colour',
        'is_active',
        'jolpica_constructor_id',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function seasonConstructors(): HasMany
    {
        return $this->hasMany(SeasonConstructor::class);
    }

    public function seasonDrivers(): HasMany
    {
        return $this->hasMany(SeasonDriver::class);
    }

    public function constructorSeasonStats(): HasMany
    {
        return $this->hasMany(ConstructorSeasonStat::class);
    }

    public function eventResults(): HasMany
    {
        return $this->hasMany(EventResult::class);
    }

    public function eventPitstops(): HasMany
    {
        return $this->hasMany(EventPitstop::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
