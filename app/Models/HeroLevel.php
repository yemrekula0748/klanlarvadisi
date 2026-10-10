<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroLevel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ability' => 'array'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(HeroType::class, 'hero_type_id');
    }
}
