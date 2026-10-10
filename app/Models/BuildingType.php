<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuildingType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['count_per_otag_level' => 'array'];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(BuildingLevel::class)->orderBy('level');
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    /** Verilen otağ seviyesinde kaç tane kurulabilir? */
    public function allowedCount(int $otagLevel): int
    {
        $map = $this->count_per_otag_level ?? [];
        $allowed = 0;
        for ($i = 1; $i <= $otagLevel; $i++) {
            if (isset($map[$i]) || isset($map[(string) $i])) {
                $allowed = (int) ($map[$i] ?? $map[(string) $i]);
            }
        }

        return $allowed;
    }

    public function isDefense(): bool
    {
        return in_array($this->category, ['savunma', 'tuzak'], true);
    }
}
