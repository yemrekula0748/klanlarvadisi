<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'shield_until'   => 'datetime',
            'guard_until'    => 'datetime',
            'last_seen_at'   => 'datetime',
            'clan_joined_at' => 'datetime',
            'is_banned'      => 'boolean',
            'gok_tasi'       => 'integer',
            'trophies'       => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function village(): HasOne
    {
        return $this->hasOne(Village::class)->where('is_capital', true);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function troops(): HasMany
    {
        return $this->hasMany(PlayerTroop::class);
    }

    public function spells(): HasMany
    {
        return $this->hasMany(PlayerSpell::class);
    }

    public function heroes(): HasMany
    {
        return $this->hasMany(PlayerHero::class);
    }

    public function attacks(): HasMany
    {
        return $this->hasMany(Battle::class, 'attacker_player_id');
    }

    public function defenses(): HasMany
    {
        return $this->hasMany(Battle::class, 'defender_player_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    /** Oyuncu kalkan altında mı? (saldırılamaz) */
    public function isProtected(): bool
    {
        return ($this->shield_until && $this->shield_until->isFuture())
            || ($this->guard_until && $this->guard_until->isFuture());
    }

    public function league(): array
    {
        $tiers = config('game.trophies.league_tiers');
        $current = $tiers[0];
        foreach ($tiers as $tier) {
            if ($this->trophies >= $tier['min']) {
                $current = $tier;
            }
        }

        return $current;
    }

    public function isClanLeader(): bool
    {
        return $this->clan_role === 'lider';
    }

    public function canManageClan(): bool
    {
        return in_array($this->clan_role, ['lider', 'vezir'], true);
    }
}
