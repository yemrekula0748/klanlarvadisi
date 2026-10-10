<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerHero extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unlocked'        => 'boolean',
            'is_upgrading'    => 'boolean',
            'upgrade_ends_at' => 'datetime',
            'regen_until'     => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(HeroType::class, 'hero_type_id');
    }

    public function isAvailable(): bool
    {
        return $this->unlocked
            && ! $this->is_upgrading
            && (! $this->regen_until || $this->regen_until->isPast());
    }
}
