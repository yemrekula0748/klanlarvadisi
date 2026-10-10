<?php

namespace App\Services;

use App\Models\Player;

class ProgressionService
{
    /** Tecrübe ekler, seviye atlatır. */
    public function addXp(Player $player, int $xp): Player
    {
        if ($xp <= 0) {
            return $player;
        }

        $player->xp += $xp;
        $player->level = $this->levelForXp((int) $player->xp);
        $player->save();

        return $player;
    }

    public function levelForXp(int $xp): int
    {
        $curve = config('game.xp_curve');
        $level = 1;
        foreach ($curve as $i => $need) {
            if ($xp >= $need) {
                $level = $i + 1;
            }
        }

        return $level;
    }

    public function xpProgress(Player $player): array
    {
        $curve = config('game.xp_curve');
        $idx = max(0, min(count($curve) - 1, $player->level - 1));
        $current = $curve[$idx] ?? 0;
        $next = $curve[$idx + 1] ?? null;

        return [
            'level'    => $player->level,
            'xp'       => (int) $player->xp,
            'current'  => $current,
            'next'     => $next,
            'percent'  => $next ? round((($player->xp - $current) / max(1, $next - $current)) * 100, 1) : 100.0,
        ];
    }

    /** Kalan süreye göre Gök Taşı maliyeti. */
    public function instantCost(int $seconds): int
    {
        if ($seconds <= 0) {
            return 0;
        }

        foreach (config('game.gok_tasi.instant_tiers') as $tier) {
            if ($seconds <= $tier['max_seconds']) {
                // Kademe içinde doğrusal ölçekleme, en az 1 Gök Taşı.
                return max(1, (int) ceil($tier['cost'] * ($seconds / $tier['max_seconds'])));
            }
        }

        return 2800;
    }
}
