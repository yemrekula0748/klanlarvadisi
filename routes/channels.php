<?php

use App\Models\Player;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Yayın Kanalları
|--------------------------------------------------------------------------
|
| Unity istemcisi Reverb'e bağlanır ve bu kanalları dinler. Yetkilendirme
| Sanctum belirteci (token) ile yapılır.
|
*/

// Oyuncunun kişisel kanalı: saldırı bildirimi, bina bitti, boy daveti...
Broadcast::channel('oyuncu.{playerId}', function ($user, int $playerId) {
    return (int) $user->player?->id === $playerId;
});

// Boy kanalı: sohbet, bağış, savaş güncellemeleri.
Broadcast::channel('boy.{clanId}', function ($user, int $clanId) {
    $player = $user->player;
    if (! $player || (int) $player->clan_id !== $clanId) {
        return false;
    }

    return ['id' => $player->id, 'name' => $player->name, 'role' => $player->clan_role];
});
