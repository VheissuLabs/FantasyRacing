<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FantasyTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'user_id',
        'name',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roster(): HasMany
    {
        return $this->hasMany(FantasyTeamRoster::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(FantasyTeamRoster::class)->where('entity_type', 'driver');
    }

    public function constructor(): ?FantasyTeamRoster
    {
        return $this->roster()->where('entity_type', 'constructor')->first();
    }

    public function inSeatDrivers(): HasMany
    {
        return $this->drivers()->where('in_seat', true);
    }

    public function benchDriver(): ?FantasyTeamRoster
    {
        return $this->drivers()->where('in_seat', false)->first();
    }

    public function fantasyPoints(): HasMany
    {
        return $this->hasMany(FantasyEventPoint::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'initiator_team_id');
    }
}
