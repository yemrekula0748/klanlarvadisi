<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $guarded = ['id'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'from_player_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'to_player_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TroopType::class, 'troop_type_id');
    }
}
