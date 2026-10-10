<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Village extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'resources_updated_at' => 'datetime',
            'last_attacked_at'     => 'datetime',
            'is_capital'           => 'boolean',
            'gold'                 => 'integer',
            'grain'                => 'integer',
            'iron'                 => 'integer',
            'kut'                  => 'integer',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function trainingQueue(): HasMany
    {
        return $this->hasMany(TrainingQueueItem::class)->orderBy('position');
    }

    public function brewingQueue(): HasMany
    {
        return $this->hasMany(BrewingQueueItem::class)->orderBy('position');
    }

    public function clanTroops(): HasMany
    {
        return $this->hasMany(ClanTroopStock::class);
    }

    public function resources(): array
    {
        return [
            'gold'  => (int) $this->gold,
            'grain' => (int) $this->grain,
            'iron'  => (int) $this->iron,
            'kut'   => (int) $this->kut,
        ];
    }
}
