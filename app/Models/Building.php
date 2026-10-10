<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Building extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_constructing'      => 'boolean',
            'construction_ends_at' => 'datetime',
            'collected_at'         => 'datetime',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class, 'building_type_id');
    }

    /** Şu anki (inşaat bitmemişse bir alt) seviyenin istatistikleri. */
    public function stats(): ?BuildingLevel
    {
        return BuildingLevel::where('building_type_id', $this->building_type_id)
            ->where('level', max(1, $this->level))
            ->first();
    }

    public function isReady(): bool
    {
        return ! $this->is_constructing;
    }

    public function remainingSeconds(): int
    {
        if (! $this->is_constructing || ! $this->construction_ends_at) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->construction_ends_at, false));
    }
}
