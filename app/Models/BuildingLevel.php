<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingLevel extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['extra' => 'array', 'attack_range' => 'float'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class, 'building_type_id');
    }

    public function costs(): array
    {
        return [
            'gold'  => (int) $this->cost_gold,
            'grain' => (int) $this->cost_grain,
            'iron'  => (int) $this->cost_iron,
            'kut'   => (int) $this->cost_kut,
        ];
    }
}
