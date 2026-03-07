<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class League extends Model
{
    use HasFactory;

    protected $fillable = [
        'franchise_id',
        'season_id',
        'name',
        'slug',
        'commissioner_id',
        'description',
        'max_teams',
        'rules',
        'invite_code',
        'visibility',
        'join_policy',
        'is_active',
        'draft_completed_at',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissioner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LeagueMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function draftSession(): HasOne
    {
        return $this->hasOne(DraftSession::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(LeagueJoinRequest::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(LeagueInvite::class);
    }

    public function freeAgentPool(): HasMany
    {
        return $this->hasMany(FreeAgentPool::class);
    }

    public function isFull(): bool
    {
        return $this->max_teams !== null && $this->members()->count() >= $this->max_teams;
    }

    public function isCommissioner(User $user): bool
    {
        return $this->commissioner_id === $user->id;
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        return data_get($this->rules, $key, $default);
    }

    public function tradeApprovalRequired(): bool
    {
        return (bool) $this->rule('trade_approval_required', true);
    }

    public function canTrade(): bool
    {
        return (bool) $this->rule('trades_enabled', true);
    }

    public function noDuplicates(): bool
    {
        return (bool) $this->rule('no_duplicates', false);
    }

    public function maxRosterSize(): ?int
    {
        $size = $this->rule('max_roster_size');

        return $size !== null ? (int) $size : null;
    }

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
            'draft_completed_at' => 'datetime',
        ];
    }
}
