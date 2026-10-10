<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Village;

class MatchmakingService
{
    public function __construct(private readonly ResourceService $resources) {}

    /**
     * Saldıracak uygun bir rakip bulur: benzer kupada, kalkansız,
     * kendi boyundan olmayan bir oyuncu.
     */
    public function findOpponent(Player $player): Village
    {
        $range = 120;

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $min = max(0, $player->trophies - $range);
            $max = $player->trophies + $range;

            $candidate = Village::query()
                ->where('is_capital', true)
                ->where('player_id', '!=', $player->id)
                ->whereHas('player', function ($q) use ($player, $min, $max) {
                    $q->whereBetween('trophies', [$min, $max])
                        ->where('is_banned', false)
                        ->where(fn ($s) => $s->whereNull('shield_until')->orWhere('shield_until', '<=', now()))
                        ->where(fn ($s) => $s->whereNull('guard_until')->orWhere('guard_until', '<=', now()));

                    if ($player->clan_id) {
                        $q->where(fn ($s) => $s->whereNull('clan_id')->orWhere('clan_id', '!=', $player->clan_id));
                    }
                })
                ->inRandomOrder()
                ->first();

            if ($candidate) {
                $this->resources->sync($candidate);

                return $candidate;
            }

            $range *= 2;
        }

        throw new GameException('Uygun rakip bulunamadı, biraz sonra tekrar dene.', 'rakip_yok', 404);
    }

    /** İntikam: son 24 saatte saldıran oyuncular. */
    public function revengeTargets(Player $player): array
    {
        $window = now()->subHours(config('game.battle.revenge_window_hours'));

        return $player->defenses()
            ->with(['attacker.village'])
            ->where('created_at', '>=', $window)
            ->where('type', 'coklu')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                'battle_id'   => $b->id,
                'player'      => [
                    'id'       => $b->attacker?->id,
                    'name'     => $b->attacker?->name,
                    'trophies' => $b->attacker?->trophies,
                ],
                'village_id'  => $b->attacker?->village?->id,
                'stars'       => $b->stars,
                'at'          => $b->created_at->toIso8601String(),
                'available'   => $b->attacker?->village && ! $b->attacker->isProtected(),
            ])
            ->all();
    }
}
