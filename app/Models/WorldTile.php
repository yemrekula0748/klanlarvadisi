<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldTile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'respawn_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(WorldRegion::class);
    }
}
