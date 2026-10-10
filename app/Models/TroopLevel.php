<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TroopLevel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['extra' => 'array'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TroopType::class, 'troop_type_id');
    }
}
