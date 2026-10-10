<?php

namespace App\Events;

use App\Models\ClanWar;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Boy savaşı durumu değişti (hazırlık -> savaş -> bitti) veya yıldız kazanıldı. */
class WarStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ClanWar $war, public string $reason = 'guncelleme') {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('boy.'.$this->war->clan_a_id),
            new PrivateChannel('boy.'.$this->war->clan_b_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'savas.guncelleme';
    }

    public function broadcastWith(): array
    {
        return [
            'war_id'      => $this->war->id,
            'state'       => $this->war->state,
            'reason'      => $this->reason,
            'stars_a'     => $this->war->stars_a,
            'stars_b'     => $this->war->stars_b,
            'destruction' => ['a' => (float) $this->war->destruction_a, 'b' => (float) $this->war->destruction_b],
            'winner'      => $this->war->winner_clan_id,
        ];
    }
}
