<?php

namespace App\Events;

use App\Models\Battle;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Oyuncuya "obana saldırıldı" bildirimi. */
class VillageAttacked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Battle $battle) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('oyuncu.'.$this->battle->defender_player_id)];
    }

    public function broadcastAs(): string
    {
        return 'oba.saldirildi';
    }

    public function broadcastWith(): array
    {
        $this->battle->loadMissing('attacker:id,name,trophies');

        return [
            'battle_id'   => $this->battle->id,
            'attacker'    => $this->battle->attacker?->only(['id', 'name', 'trophies']),
            'stars'       => $this->battle->stars,
            'destruction' => (float) $this->battle->destruction_percent,
            'loot'        => $this->battle->loot(),
            'trophies'    => $this->battle->trophies_defender,
            'at'          => $this->battle->created_at->toIso8601String(),
        ];
    }
}
