<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\ChatMessage;
use App\Models\Clan;
use App\Models\ClanJoinRequest;
use App\Models\ClanTroopStock;
use App\Models\Donation;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\TroopRequest;
use App\Models\TroopType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClanService
{
    public function create(Player $player, array $data): Clan
    {
        if ($player->clan_id) {
            throw new GameException('Zaten bir boya bağlısın.', 'boyda');
        }

        return DB::transaction(function () use ($player, $data) {
            $clan = Clan::create([
                'name'              => $data['name'],
                'tag'               => $this->generateTag(),
                'description'       => $data['description'] ?? '',
                'badge'             => $data['badge'] ?? $this->randomBadge(),
                'type'              => $data['type'] ?? 'acik',
                'required_trophies' => (int) ($data['required_trophies'] ?? 0),
                'war_frequency'     => $data['war_frequency'] ?? 'sik',
                'leader_player_id'  => $player->id,
                'member_count'      => 1,
                'member_limit'      => config('game.clan.member_limit'),
                'points'            => $player->trophies,
            ]);

            $player->update([
                'clan_id'        => $clan->id,
                'clan_role'      => 'lider',
                'clan_joined_at' => now(),
            ]);

            $this->systemMessage($clan, "{$player->name} boyu kurdu. Tengri yardımcısı olsun!");

            return $clan;
        });
    }

    private function generateTag(): string
    {
        $len = config('game.clan.tag_length');
        do {
            $tag = Str::upper(Str::random($len));
        } while (Clan::where('tag', $tag)->exists());

        return $tag;
    }

    public function randomBadge(): array
    {
        $shapes = ['kalkan', 'tug', 'ay', 'kurt', 'gunes'];
        $symbols = ['kurt_basi', 'konrul', 'gok_kurt', 'ok_yay', 'damga', 'bogaz', 'yelbegen', 'umay'];
        $palettes = [
            ['#c0392b', '#f1c40f'], ['#1f6f8b', '#e8eef2'], ['#2d6a4f', '#ffd166'],
            ['#4a2d6a', '#e0a63c'], ['#7a1f1f', '#d9c8a9'], ['#0f4c5c', '#f4a261'],
        ];
        $p = $palettes[array_rand($palettes)];

        return [
            'shape'  => $shapes[array_rand($shapes)],
            'symbol' => $symbols[array_rand($symbols)],
            'colors' => $p,
        ];
    }

    public function join(Player $player, Clan $clan, string $message = ''): array
    {
        if ($player->clan_id) {
            throw new GameException('Zaten bir boya bağlısın.', 'boyda');
        }
        if ($clan->member_count >= $clan->member_limit) {
            throw new GameException('Boy dolu.', 'boy_dolu');
        }
        if ($player->trophies < $clan->required_trophies) {
            throw new GameException("Bu boy en az {$clan->required_trophies} kupa istiyor.", 'kupa_yetersiz');
        }
        if ($clan->type === 'kapali') {
            throw new GameException('Bu boy yeni üye almıyor.', 'boy_kapali');
        }

        if ($clan->type === 'davet') {
            ClanJoinRequest::updateOrCreate(
                ['clan_id' => $clan->id, 'player_id' => $player->id],
                ['message' => $message, 'status' => 'bekliyor']
            );

            return ['status' => 'istek_gonderildi'];
        }

        $this->addMember($player, $clan);

        return ['status' => 'katildi'];
    }

    public function addMember(Player $player, Clan $clan): void
    {
        DB::transaction(function () use ($player, $clan) {
            $player->update([
                'clan_id'        => $clan->id,
                'clan_role'      => 'er',
                'clan_joined_at' => now(),
            ]);

            $clan->update([
                'member_count' => $clan->members()->count(),
                'points'       => (int) $clan->members()->sum('trophies'),
            ]);

            ClanJoinRequest::where('clan_id', $clan->id)->where('player_id', $player->id)->delete();
            $this->systemMessage($clan, "{$player->name} boya katıldı.");
        });
    }

    public function leave(Player $player): void
    {
        $clan = $player->clan;
        if (! $clan) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        DB::transaction(function () use ($player, $clan) {
            $wasLeader = $player->isClanLeader();

            $player->update([
                'clan_id'        => null,
                'clan_role'      => null,
                'clan_joined_at' => null,
            ]);

            $remaining = $clan->members()->count();

            if ($remaining === 0) {
                $clan->delete();

                return;
            }

            if ($wasLeader) {
                // Liderlik en yüksek rütbeli / kupalı üyeye geçer.
                $heir = $clan->members()->first();
                $heir?->update(['clan_role' => 'lider']);
                $clan->update(['leader_player_id' => $heir?->id]);
                $this->systemMessage($clan, "{$heir?->name} boyun yeni başı oldu.");
            }

            $clan->update([
                'member_count' => $remaining,
                'points'       => (int) $clan->members()->sum('trophies'),
            ]);

            $this->systemMessage($clan, "{$player->name} boydan ayrıldı.");
        });
    }

    public function kick(Player $actor, Player $target): void
    {
        $this->assertSameClan($actor, $target);

        if (! $actor->canManageClan()) {
            throw GameException::izinYok('Üye çıkarmak için vezir olmalısın.');
        }
        if ($target->isClanLeader()) {
            throw GameException::izinYok('Boy başı çıkarılamaz.');
        }
        if ($actor->clan_role === 'vezir' && $target->clan_role === 'vezir') {
            throw GameException::izinYok('Vezirler birbirini çıkaramaz.');
        }

        $clan = $actor->clan;
        $name = $target->name;

        $target->update(['clan_id' => null, 'clan_role' => null, 'clan_joined_at' => null]);
        $clan->update([
            'member_count' => $clan->members()->count(),
            'points'       => (int) $clan->members()->sum('trophies'),
        ]);

        GameEvent::create([
            'player_id' => $target->id,
            'type'      => 'boydan_cikarildi',
            'title'     => 'Boydan çıkarıldın',
            'body'      => "{$clan->name} boyundan çıkarıldın.",
        ]);

        $this->systemMessage($clan, "{$name} boydan çıkarıldı.");
    }

    public function setRole(Player $actor, Player $target, string $role): void
    {
        $this->assertSameClan($actor, $target);

        if (! in_array($role, ['vezir', 'alp', 'er'], true)) {
            throw new GameException('Geçersiz rütbe.', 'gecersiz_rutbe');
        }
        if (! $actor->isClanLeader()) {
            throw GameException::izinYok('Rütbe vermek yalnızca boy başının hakkıdır.');
        }

        $target->update(['clan_role' => $role]);
        $labels = ['vezir' => 'Vezir', 'alp' => 'Alp', 'er' => 'Er'];
        $this->systemMessage($actor->clan, "{$target->name} artık {$labels[$role]}.");
    }

    public function transferLeadership(Player $leader, Player $target): void
    {
        $this->assertSameClan($leader, $target);

        if (! $leader->isClanLeader()) {
            throw GameException::izinYok('Yalnızca boy başı liderliği devredebilir.');
        }

        DB::transaction(function () use ($leader, $target) {
            $leader->update(['clan_role' => 'vezir']);
            $target->update(['clan_role' => 'lider']);
            $leader->clan->update(['leader_player_id' => $target->id]);
            $this->systemMessage($leader->clan, "Boy başlığı {$target->name}'e geçti.");
        });
    }

    /** Asker bağışı: Boy Otağı'na asker gönderir. */
    public function donate(Player $donor, TroopRequest $request, string $troopKey, int $quantity): Donation
    {
        if ($donor->clan_id !== $request->clan_id) {
            throw GameException::izinYok('Yalnızca kendi boyuna bağış yapabilirsin.');
        }
        if ($donor->id === $request->player_id) {
            throw GameException::izinYok('Kendine bağış yapamazsın.');
        }
        if ($request->expires_at->isPast()) {
            throw new GameException('Bu istek zaman aşımına uğradı.', 'istek_bitti');
        }

        $type = TroopType::where('key', $troopKey)->firstOr(
            fn () => throw GameException::bulunamadi('Asker tipi bulunamadı.')
        );

        $pt = $donor->troops()->where('troop_type_id', $type->id)->first();
        if (! $pt || $pt->ready < $quantity) {
            throw new GameException('Bağışlayacak askerin yok.', 'asker_yetersiz');
        }

        $space = $quantity * $type->housing;
        if ($request->fulfilled + $space > $request->capacity) {
            throw new GameException('Boy Otağı dolu.', 'otag_dolu');
        }

        return DB::transaction(function () use ($donor, $request, $type, $pt, $quantity, $space) {
            $pt->decrement('ready', $quantity);

            $receiver = $request->player;
            $village = $receiver->village;

            // DB::raw('quantity + n') INSERT sırasında çalışmaz (kolon henüz
            // yok); bu yüzden kaydı bul-veya-yarat, sonra arttır.
            $stock = ClanTroopStock::firstOrCreate(
                ['village_id' => $village->id, 'troop_type_id' => $type->id, 'level' => $pt->level],
                ['quantity' => 0]
            );
            $stock->increment('quantity', $quantity);

            $request->increment('fulfilled', $space);
            $donor->increment('donated', $quantity);
            $receiver->increment('received', $quantity);

            $clan = $donor->clan;
            $clan->increment('xp', $quantity);
            $clan->update(['level' => $clan->levelFromXp()]);

            ChatMessage::create([
                'scope'     => 'boy',
                'clan_id'   => $clan->id,
                'player_id' => $donor->id,
                'kind'      => 'bagis',
                'body'      => "{$quantity} adet {$type->name} bağışladı.",
                'meta'      => ['troop' => $type->key, 'quantity' => $quantity, 'to' => $receiver->name],
            ]);

            return Donation::create([
                'clan_id'        => $clan->id,
                'from_player_id' => $donor->id,
                'to_player_id'   => $receiver->id,
                'troop_type_id'  => $type->id,
                'quantity'       => $quantity,
                'level'          => $pt->level,
            ]);
        });
    }

    public function requestTroops(Player $player, string $message): TroopRequest
    {
        if (! $player->clan_id) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        $village = $player->village;
        $capacity = 0;
        foreach ($village->buildings()->with('type')->get() as $b) {
            if ($b->type?->key === 'boy_otagi' && ! $b->is_constructing) {
                $capacity = (int) ($b->stats()?->troop_capacity ?? 0);
            }
        }

        if ($capacity <= 0) {
            throw new GameException('Önce Boy Otağı kurmalısın.', 'boy_otagi_yok');
        }

        TroopRequest::where('player_id', $player->id)->where('expires_at', '>', now())->delete();

        $request = TroopRequest::create([
            'clan_id'    => $player->clan_id,
            'player_id'  => $player->id,
            'message'    => $message,
            'capacity'   => $capacity,
            'expires_at' => now()->addHours(6),
        ]);

        ChatMessage::create([
            'scope'     => 'boy',
            'clan_id'   => $player->clan_id,
            'player_id' => $player->id,
            'kind'      => 'istek',
            'body'      => $message ?: 'Asker gönderin yiğitler!',
            'meta'      => ['request_id' => $request->id, 'capacity' => $capacity],
        ]);

        return $request;
    }

    public function systemMessage(Clan $clan, string $body): void
    {
        ChatMessage::create([
            'scope'   => 'boy',
            'clan_id' => $clan->id,
            'kind'    => 'sistem',
            'body'    => $body,
        ]);
    }

    private function assertSameClan(Player $a, Player $b): void
    {
        if (! $a->clan_id || $a->clan_id !== $b->clan_id) {
            throw GameException::izinYok('Bu oyuncu senin boyunda değil.');
        }
    }
}
