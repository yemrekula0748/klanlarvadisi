<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanJoinRequest extends Model
{
    protected $guarded = ['id'];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
