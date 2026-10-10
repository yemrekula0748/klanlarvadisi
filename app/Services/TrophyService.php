<?php

namespace App\Services;

use App\Models\Player;

class TrophyService
{
    /**
     * Kupa değişimini hesaplar ve uygular.
     *
     * @return array{0:int,1:int} [saldıranın değişimi, savunanın değişimi]
     */
    public function resolve(Player $attacker, Player $defender, int $stars, float $destruction): array
    {
        $k = (int) config('game.trophies.k_factor');
        $min = (int) config('game.trophies.min_gain');
        $max = (int) config('game.trophies.max_gain');

        // Beklenen başarı (Elo): güçlü rakibi yenmek daha çok kupa getirir.
        $diff = $defender->trophies - $attacker->trophies;
        $expected = 1 / (1 + pow(10, -$diff / 400));

        // Gerçek skor: yıldız ve yıkım oranının birleşimi.
        $score = min(1.0, ($stars / 3) * 0.7 + ($destruction / 100) * 0.3);

        $delta = (int) round($k * ($score - (1 - $expected)) + $score * 12);

        if ($stars > 0) {
            $gain = max($min, min($max, abs($delta)));
            $loss = -max($min, min($max, (int) round($gain * 0.9)));
        } else {
            $gain = -max($min, min(20, (int) round(($expected) * 14)));
            $loss = max(1, (int) round(abs($gain) * 0.6));
        }

        $attacker->trophies = max(0, $attacker->trophies + $gain);
        $attacker->best_trophies = max($attacker->best_trophies, $attacker->trophies);
        $attacker->save();

        $defender->trophies = max(0, $defender->trophies + $loss);
        $defender->best_trophies = max($defender->best_trophies, $defender->trophies);
        $defender->save();

        $this->syncClanPoints($attacker);
        $this->syncClanPoints($defender);

        return [$gain, $loss];
    }

    public function syncClanPoints(Player $player): void
    {
        if (! $player->clan_id) {
            return;
        }

        $clan = $player->clan;
        if (! $clan) {
            return;
        }

        $clan->update([
            'points'       => (int) $clan->members()->sum('trophies'),
            'member_count' => $clan->members()->count(),
        ]);
    }
}
