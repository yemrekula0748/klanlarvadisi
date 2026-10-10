<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Building;
use App\Models\BuildingLevel;
use App\Models\Village;
use Illuminate\Support\Carbon;

/**
 * Kaynak üretimi "tembel" (lazy) hesaplanır: her okumada, son güncelleme
 * anından bu yana geçen süre ile üretim hızı çarpılır. Böylece milyonlarca
 * oba için arka planda iş çalıştırmak gerekmez.
 */
class ResourceService
{
    /** Obanın kaynaklarını şimdiki ana kadar işler ve kaydeder. */
    public function sync(Village $village, ?Carbon $now = null): Village
    {
        $now ??= now();
        $last = $village->resources_updated_at ?? $village->created_at ?? $now;
        $seconds = $last->diffInSeconds($now, false);

        if ($seconds <= 0) {
            return $village;
        }

        $rates = $this->productionRates($village);
        $caps = $this->storageCaps($village);

        foreach (config('game.resources') as $res) {
            $produced = (int) floor(($rates[$res] / 3600) * $seconds);
            if ($produced <= 0) {
                continue;
            }
            $village->{$res} = (int) min($caps[$res], $village->{$res} + $produced);
        }

        $village->resources_updated_at = $now;
        $village->save();

        return $village;
    }

    /** Saatlik üretim (bina seviyelerinin toplamı). */
    public function productionRates(Village $village): array
    {
        $rates = array_fill_keys(config('game.resources'), 0);

        $buildings = $village->relationLoaded('buildings')
            ? $village->buildings
            : $village->buildings()->with('type')->get();

        foreach ($buildings as $b) {
            $type = $b->type;
            if (! $type || ! $type->produces || $b->is_constructing && $b->level === 0) {
                continue;
            }
            $stats = $this->levelStats($b);
            if (! $stats) {
                continue;
            }
            $rates[$type->produces] += (int) $stats->production_per_hour;
        }

        return $rates;
    }

    /** Depo kapasiteleri (Otağ + depo binaları). */
    public function storageCaps(Village $village): array
    {
        $caps = array_fill_keys(config('game.resources'), 0);

        $buildings = $village->relationLoaded('buildings')
            ? $village->buildings
            : $village->buildings()->with('type')->get();

        foreach ($buildings as $b) {
            $type = $b->type;
            if (! $type) {
                continue;
            }
            $stats = $this->levelStats($b);
            if (! $stats || $stats->storage_capacity <= 0) {
                continue;
            }

            // Otağ her kaynağı bir miktar depolar; özel depolar tek kaynağı.
            if ($type->stores === null && $type->key === 'otag') {
                foreach ($caps as $res => $_) {
                    $caps[$res] += (int) $stats->storage_capacity;
                }
            } elseif ($type->stores) {
                $caps[$type->stores] += (int) $stats->storage_capacity;
            }
        }

        return $caps;
    }

    /** İnşaat sürerken bina bir önceki seviyenin istatistiklerini kullanır. */
    private function levelStats(Building $b): ?BuildingLevel
    {
        $level = $b->is_constructing ? max(0, $b->level) : $b->level;
        if ($level < 1) {
            return null;
        }

        return BuildingLevel::where('building_type_id', $b->building_type_id)
            ->where('level', $level)
            ->first();
    }

    /** Kaynak yeterli mi? */
    public function canAfford(Village $village, array $costs): bool
    {
        foreach ($costs as $res => $amount) {
            if ($amount > 0 && $village->{$res} < $amount) {
                return false;
            }
        }

        return true;
    }

    /** Kaynak harcar; yetersizse GameException atar. */
    public function spend(Village $village, array $costs): void
    {
        foreach ($costs as $res => $amount) {
            if ($amount > 0 && $village->{$res} < $amount) {
                throw GameException::kaynakYetersiz($res);
            }
        }

        foreach ($costs as $res => $amount) {
            if ($amount > 0) {
                $village->{$res} -= (int) $amount;
            }
        }

        $village->save();
    }

    /** Depo sınırını aşmadan kaynak ekler; eklenen miktarı döndürür. */
    public function add(Village $village, array $amounts): array
    {
        $caps = $this->storageCaps($village);
        $added = [];

        foreach ($amounts as $res => $amount) {
            if ($amount <= 0 || ! in_array($res, config('game.resources'), true)) {
                continue;
            }
            $before = (int) $village->{$res};
            // Kapasite sınırı eklemeyi kısar, ama mevcut bakiyeyi düşürmez:
            // kapasitenin üstünde bir bakiye varsa (ödül, depo yıkımı) olduğu
            // gibi kalır.
            $village->{$res} = (int) max($before, min($caps[$res], $before + (int) $amount));
            $added[$res] = (int) $village->{$res} - $before;
        }

        $village->save();

        return $added;
    }

    /** Yağmalanabilir kaynak miktarı (saldırganın otağ seviyesine göre). */
    public function lootable(Village $defender, int $attackerOtagLevel): array
    {
        $cfg = config('game.battle.loot_percent');
        $diff = max(0, $defender->otag_level - $attackerOtagLevel);
        $percent = max($cfg['min'], $cfg['base'] - $diff * $cfg['per_level_penalty']);

        return [
            'gold'  => (int) floor($defender->gold * $percent),
            'grain' => (int) floor($defender->grain * $percent),
            'iron'  => (int) floor($defender->iron * $percent),
            'kut'   => (int) floor($defender->kut * $percent * 0.5),
        ];
    }
}
