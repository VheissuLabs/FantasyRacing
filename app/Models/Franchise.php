<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Franchise extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'is_active',
    ];

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }

    public function constructors(): HasMany
    {
        return $this->hasMany(Constructor::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'franchise_managers')
            ->withTimestamps()
            ->withPivot('created_at');
    }

    public function pointsSchemes(): HasMany
    {
        return $this->hasMany(PointsScheme::class);
    }

    public function bonusPointsSchemes(): HasMany
    {
        return $this->hasMany(BonusPointsScheme::class);
    }

    public function activeSeason(): ?Season
    {
        return $this->seasons()->where('is_active', true)->first();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
