<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQueueItem extends Model
{
    protected $table = 'training_queue';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TroopType::class, 'troop_type_id');
    }

    /** Bu kalemden kaç asker tamamlandı? */
    public function completedCount(): int
    {
        $elapsed = $this->starts_at->diffInSeconds(now(), false);
        if ($elapsed <= 0 || $this->seconds_each <= 0) {
            return 0;
        }

        return min($this->quantity, intdiv($elapsed, $this->seconds_each));
    }
}
