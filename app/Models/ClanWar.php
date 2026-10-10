<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClanWar extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'prep_ends_at'   => 'datetime',
            'battle_ends_at' => 'datetime',
            'destruction_a'  => 'float',
            'destruction_b'  => 'float',
        ];
    }

    public function clanA(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'clan_a_id');
    }

    public function clanB(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'clan_b_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClanWarMember::class)->orderBy('map_position');
    }

    public function attacks(): HasMany
    {
        return $this->hasMany(ClanWarAttack::class);
    }

    public function opponentOf(int $clanId): int
    {
        return $this->clan_a_id === $clanId ? $this->clan_b_id : $this->clan_a_id;
    }

    public function isBattleDay(): bool
    {
        return $this->state === 'savas';
    }
}
