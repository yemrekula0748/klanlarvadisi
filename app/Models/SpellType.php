<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpellType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['radius' => 'float'];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(SpellLevel::class)->orderBy('level');
    }

    public function statsAt(int $level): ?SpellLevel
    {
        return SpellLevel::where('spell_type_id', $this->id)->where('level', $level)->first();
    }
}
