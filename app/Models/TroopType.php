<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TroopType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_air'                   => 'boolean',
            'targets_air'              => 'boolean',
            'move_speed'               => 'float',
            'attack_range'             => 'float',
            'attack_speed'             => 'float',
            'splash_radius'            => 'float',
            'target_damage_multiplier' => 'float',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(TroopLevel::class)->orderBy('level');
    }

    public function statsAt(int $level): ?TroopLevel
    {
        return $this->levels->firstWhere('level', $level)
            ?? TroopLevel::where('troop_type_id', $this->id)->where('level', $level)->first();
    }

    public function trainCosts(): array
    {
        return [
            'gold'  => (int) $this->train_cost_gold,
            'grain' => (int) $this->train_cost_grain,
            'iron'  => (int) $this->train_cost_iron,
            'kut'   => (int) $this->train_cost_kut,
        ];
    }
}
