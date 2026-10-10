<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanTroopStock extends Model
{
    protected $guarded = ['id'];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TroopType::class, 'troop_type_id');
    }
}
