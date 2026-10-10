<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\PlayerSpell;
use App\Models\PlayerTroop;
use App\Models\SpellType;
use App\Models\TrainingQueueItem;
use App\Models\BrewingQueueItem;
use App\Models\TroopType;
use App\Models\Village;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    public function __construct(private readonly ResourceService $resources) {}

    /** Tamamlanan eğitimleri askere dönüştürür. */
    public function settle(Village $village): void
    {
        $player = $village->player;

        foreach ($village->trainingQueue()->with('type')->get() as $item) {
            $done = $item->completedCount();
            if ($done <= 0) {
                continue;
            }

            $pt = PlayerTroop::firstOrCreate(
                ['player_id' => $player->id, 'troop_type_id' => $item->troop_type_id],
                ['level' => 1]
            );
            $pt->increment('ready', $done);

            if ($done >= $item->quantity) {
                $item->delete();
            } else {
                $item->update([
                    'quantity'  => $item->quantity - $done,
                    'starts_at' => $item->starts_at->copy()->addSeconds($done * $item->seconds_each),
                ]);
            }
        }

        foreach ($village->brewingQueue()->with('type')->get() as $item) {
            $done = $item->completedCount();
            if ($done <= 0) {
                continue;
            }

            $ps = PlayerSpell::firstOrCreate(
                ['player_id' => $player->id, 'spell_type_id' => $item->spell_type_id],
                ['level' => 1]
            );
            $ps->increment('ready', $done);

            if ($done >= $item->quantity) {
                $item->delete();
            } else {
                $item->update([
                    'quantity'  => $item->quantity - $done,
                    'starts_at' => $item->starts_at->copy()->addSeconds($done * $item->seconds_each),
                ]);
            }
        }

        $this->resolveUpgrades($village);
    }

    private function resolveUpgrades(Village $village): void
    {
        PlayerTroop::where('player_id', $village->player_id)
            ->where('is_upgrading', true)
            ->where('upgrade_ends_at', '<=', now())
            ->get()
            ->each(function (PlayerTroop $pt) {
                $pt->update([
                    'level'           => $pt->level + 1,
                    'is_upgrading'    => false,
                    'upgrade_ends_at' => null,
                ]);
            });

        PlayerSpell::where('player_id', $village->player_id)
            ->where('is_upgrading', true)
            ->where('upgrade_ends_at', '<=', now())
            ->get()
            ->each(function (PlayerSpell $ps) {
                $ps->update([
                    'level'           => $ps->level + 1,
                    'is_upgrading'    => false,
                    'upgrade_ends_at' => null,
                ]);
            });
    }

    /** Ordugâhların toplam asker kapasitesi. */
    public function armyCapacity(Village $village): int
    {
        $total = 0;
        foreach ($village->buildings()->with('type')->get() as $b) {
            if ($b->is_constructing && $b->level < 1) {
                continue;
            }
            if ($b->type?->category !== 'ordu') {
                continue;
            }
            $stats = $b->stats();
            $total += (int) ($stats?->troop_capacity ?? 0);
        }

        return $total;
    }

    /** Kullanılan asker alanı (hazır + kuyrukta). */
    public function armyUsage(Village $village): array
    {
        $player = $village->player;
        $used = 0;

        foreach ($player->troops()->with('type')->get() as $pt) {
            $used += $pt->ready * ($pt->type->housing ?? 1);
        }

        $queued = 0;
        foreach ($village->trainingQueue()->with('type')->get() as $item) {
            $queued += $item->quantity * ($item->type->housing ?? 1);
        }

        return [
            'ready'    => $used,
            'queued'   => $queued,
            'capacity' => $this->armyCapacity($village),
        ];
    }

    public function spellCapacity(Village $village): int
    {
        $total = 0;
        foreach ($village->buildings()->with('type')->get() as $b) {
            if ($b->type?->key !== 'buyu_atolyesi' || ($b->is_constructing && $b->level < 1)) {
                continue;
            }
            $stats = $b->stats();
            $total += (int) ($stats?->extra['spell_capacity'] ?? 0);
        }

        return $total;
    }

    /** Asker eğitim kuyruğuna ekler. */
    public function train(Village $village, string $troopKey, int $quantity): TrainingQueueItem
    {
        if ($quantity < 1 || $quantity > 200) {
            throw new GameException('Geçersiz asker sayısı.', 'gecersiz_adet');
        }

        $this->settle($village);
        $this->resources->sync($village);

        $type = TroopType::where('key', $troopKey)->firstOr(
            fn () => throw GameException::bulunamadi("Asker tipi bulunamadı: $troopKey")
        );

        $kisla = $this->bestLevelOf($village, $type->category === 'karanlik' ? 'kara_kisla' : 'kisla');
        if ($kisla < $type->unlock_kisla_level) {
            throw new GameException(
                "{$type->name} için Kışla {$type->unlock_kisla_level}. seviyede olmalı.",
                'kisla_seviyesi'
            );
        }

        $usage = $this->armyUsage($village);
        $need = $quantity * $type->housing;
        if ($usage['ready'] + $usage['queued'] + $need > $usage['capacity']) {
            throw new GameException('Ordugâhlarda yeterli yer yok.', 'ordu_dolu');
        }

        $costs = array_map(fn ($v) => $v * $quantity, $type->trainCosts());

        return DB::transaction(function () use ($village, $type, $quantity, $costs) {
            $this->resources->spend($village, $costs);

            $last = $village->trainingQueue()->orderByDesc('position')->first();
            $startsAt = $last && $last->ends_at->isFuture() ? $last->ends_at : now();
            $seconds = (int) $type->train_seconds;

            return TrainingQueueItem::create([
                'village_id'    => $village->id,
                'troop_type_id' => $type->id,
                'quantity'      => $quantity,
                'seconds_each'  => $seconds,
                'starts_at'     => $startsAt,
                'ends_at'       => $startsAt->copy()->addSeconds($seconds * $quantity),
                'position'      => ($last?->position ?? 0) + 1,
            ]);
        });
    }

    /** Kuyruktaki eğitimi iptal eder, kaynağın tamamını geri verir. */
    public function cancelTraining(Village $village, int $itemId, int $quantity = 0): void
    {
        $item = $village->trainingQueue()->with('type')->findOr(
            $itemId,
            fn () => throw GameException::bulunamadi('Kuyruk kalemi bulunamadı.')
        );

        $this->settle($village);
        $item = TrainingQueueItem::find($itemId);
        if (! $item) {
            return;
        }

        $quantity = $quantity > 0 ? min($quantity, $item->quantity) : $item->quantity;
        $refund = array_map(fn ($v) => $v * $quantity, $item->type->trainCosts());

        DB::transaction(function () use ($village, $item, $quantity, $refund) {
            $this->resources->add($village, $refund);

            if ($quantity >= $item->quantity) {
                $item->delete();
            } else {
                $item->decrement('quantity', $quantity);
            }
        });
    }

    /** Tılsım (büyü) hazırlar. */
    public function brew(Village $village, string $spellKey, int $quantity): BrewingQueueItem
    {
        $this->settle($village);
        $this->resources->sync($village);

        $type = SpellType::where('key', $spellKey)->firstOr(
            fn () => throw GameException::bulunamadi("Tılsım bulunamadı: $spellKey")
        );

        $atolye = $this->bestLevelOf($village, 'buyu_atolyesi');
        if ($atolye < $type->unlock_buyu_level) {
            throw new GameException(
                "{$type->name} için Büyü Atölyesi {$type->unlock_buyu_level}. seviyede olmalı.",
                'atolye_seviyesi'
            );
        }

        $capacity = $this->spellCapacity($village);
        $held = $village->player->spells()->with('type')->get()
            ->sum(fn ($ps) => $ps->ready * ($ps->type->housing ?? 1));
        $queued = $village->brewingQueue()->with('type')->get()
            ->sum(fn ($b) => $b->quantity * ($b->type->housing ?? 1));

        if ($held + $queued + $quantity * $type->housing > $capacity) {
            throw new GameException('Büyü Atölyesi dolu.', 'atolye_dolu');
        }

        $costs = ['kut' => $type->cost_kut * $quantity, 'iron' => $type->cost_iron * $quantity];

        return DB::transaction(function () use ($village, $type, $quantity, $costs) {
            $this->resources->spend($village, $costs);

            $last = $village->brewingQueue()->orderByDesc('position')->first();
            $startsAt = $last && $last->ends_at->isFuture() ? $last->ends_at : now();

            return BrewingQueueItem::create([
                'village_id'    => $village->id,
                'spell_type_id' => $type->id,
                'quantity'      => $quantity,
                'seconds_each'  => (int) $type->brew_seconds,
                'starts_at'     => $startsAt,
                'ends_at'       => $startsAt->copy()->addSeconds($type->brew_seconds * $quantity),
                'position'      => ($last?->position ?? 0) + 1,
            ]);
        });
    }

    /** Demirci'de asker seviyesi yükseltir. */
    public function upgradeTroop(Village $village, string $troopKey): PlayerTroop
    {
        $this->settle($village);
        $this->resources->sync($village);

        $type = TroopType::where('key', $troopKey)->firstOr(
            fn () => throw GameException::bulunamadi('Asker tipi bulunamadı.')
        );

        $pt = PlayerTroop::firstOrCreate(
            ['player_id' => $village->player_id, 'troop_type_id' => $type->id],
            ['level' => 1]
        );

        if ($pt->is_upgrading) {
            throw new GameException('Bu asker hâlihazırda geliştiriliyor.', 'gelistirme_suruyor');
        }

        $next = $pt->level + 1;
        if ($next > $type->max_level) {
            throw new GameException("{$type->name} azami seviyede.", 'azami_seviye');
        }

        $stats = $type->statsAt($next);
        if (! $stats) {
            throw GameException::bulunamadi('Seviye tanımsız.');
        }

        $demirci = $this->bestLevelOf($village, 'demirci');
        if ($demirci < $stats->requires_demirci_level) {
            throw new GameException(
                "Bu seviye için Demirci {$stats->requires_demirci_level}. seviyede olmalı.",
                'demirci_seviyesi'
            );
        }

        return DB::transaction(function () use ($village, $pt, $stats) {
            $this->resources->spend($village, [
                'iron' => (int) $stats->upgrade_cost_iron,
                'kut'  => (int) $stats->upgrade_cost_kut,
            ]);

            $pt->update([
                'is_upgrading'    => true,
                'upgrade_ends_at' => now()->addSeconds($stats->upgrade_seconds),
            ]);

            return $pt->refresh();
        });
    }

    /** Obadaki belirli bir bina tipinin en yüksek hazır seviyesi. */
    public function bestLevelOf(Village $village, string $typeKey): int
    {
        return (int) $village->buildings()
            ->whereHas('type', fn ($q) => $q->where('key', $typeKey))
            ->where('is_constructing', false)
            ->max('level');
    }
}
