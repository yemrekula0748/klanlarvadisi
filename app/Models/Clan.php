<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['badge' => 'array'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Player::class)->orderByRaw(
            "CASE clan_role WHEN 'lider' THEN 0 WHEN 'vezir' THEN 1 WHEN 'alp' THEN 2 ELSE 3 END"
        )->orderByDesc('trophies');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'leader_player_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ClanJoinRequest::class)->where('status', 'bekliyor');
    }

    public function wars(): HasMany
    {
        return $this->hasMany(ClanWar::class, 'clan_a_id');
    }

    public function currentWar(): ?ClanWar
    {
        return ClanWar::where(fn ($q) => $q->where('clan_a_id', $this->id)->orWhere('clan_b_id', $this->id))
            ->whereIn('state', ['hazirlik', 'savas'])
            ->latest('id')
            ->first();
    }

    public function levelFromXp(): int
    {
        $curve = config('game.clan.levels');
        $level = 1;
        foreach ($curve as $i => $need) {
            if ($this->xp >= $need) {
                $level = $i + 1;
            }
        }

        return $level;
    }
}
