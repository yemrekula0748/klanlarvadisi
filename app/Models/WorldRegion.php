<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldRegion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['bonus' => 'array'];
    }

    public function controllingClan(): BelongsTo
    {
        return $this->belongsTo(Clan::class, 'controlling_clan_id');
    }

    public function contains(int $x, int $y): bool
    {
        $dx = $x - $this->center_x;
        $dy = $y - $this->center_y;

        return ($dx * $dx + $dy * $dy) <= ($this->radius * $this->radius);
    }
}
