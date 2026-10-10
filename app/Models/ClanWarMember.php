<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanWarMember extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'layout'                   => 'array',
            'best_destruction_against' => 'float',
        ];
    }

    public function war(): BelongsTo
    {
        return $this->belongsTo(ClanWar::class, 'clan_war_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }
}
