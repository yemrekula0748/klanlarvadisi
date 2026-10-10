<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Boy sohbeti canlı yayını. Unity, Reverb'e (Pusher protokolü) bağlanıp
 * `boy.{id}` kanalını dinler.
 */
class ClanChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('boy.'.$this->message->clan_id)];
    }

    public function broadcastAs(): string
    {
        return 'boy.sohbet';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('player:id,name,avatar');

        return [
            'id'     => $this->message->id,
            'kind'   => $this->message->kind,
            'body'   => $this->message->body,
            'meta'   => $this->message->meta,
            'player' => $this->message->player?->only(['id', 'name', 'avatar']),
            'at'     => $this->message->created_at->toIso8601String(),
        ];
    }
}
