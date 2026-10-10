<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpellLevel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effect' => 'array'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(SpellType::class, 'spell_type_id');
    }
}
