<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeroType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_air'       => 'boolean',
            'move_speed'   => 'float',
            'attack_range' => 'float',
            'attack_speed' => 'float',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(HeroLevel::class)->orderBy('level');
    }

    public function statsAt(int $level): ?HeroLevel
    {
        return HeroLevel::where('hero_type_id', $this->id)->where('level', $level)->first();
    }
}
