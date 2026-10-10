<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerTroop extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_upgrading'    => 'boolean',
            'upgrade_ends_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TroopType::class, 'troop_type_id');
    }
}
