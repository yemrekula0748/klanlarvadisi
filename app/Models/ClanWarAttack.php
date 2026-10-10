<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanWarAttack extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['destruction_percent' => 'float'];
    }

    public function war(): BelongsTo
    {
        return $this->belongsTo(ClanWar::class, 'clan_war_id');
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function attacker(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'attacker_player_id');
    }

    public function defender(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'defender_player_id');
    }
}
