<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Battle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'army'                => 'array',
            'layout'              => 'array',
            'replay'              => 'array',
            'destruction_percent' => 'float',
        ];
    }

    public function attacker(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'attacker_player_id');
    }

    public function defender(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'defender_player_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'defender_village_id');
    }

    public function loot(): array
    {
        return [
            'gold'  => (int) $this->loot_gold,
            'grain' => (int) $this->loot_grain,
            'iron'  => (int) $this->loot_iron,
            'kut'   => (int) $this->loot_kut,
        ];
    }
}
