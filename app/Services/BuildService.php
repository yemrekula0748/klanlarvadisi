<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Building;
use App\Models\BuildingLevel;
use App\Models\BuildingType;
use App\Models\Village;
use Illuminate\Support\Facades\DB;

class BuildService
{
    public function __construct(
        private readonly ResourceService $resources,
        private readonly ProgressionService $progression,
    ) {}

    /** Obadaki tamamlanmış inşaatları uygular (tembel çözümleme). */
    public function settle(Village $village): void
    {
        $done = $village->buildings()
            ->where('is_constructing', true)
            ->where('construction_ends_at', '<=', now())
            ->get();

        if ($done->isEmpty()) {
            return;
        }

        foreach ($done as $b) {
            $target = $b->target_level ?? max(1, $b->level + 1);
            $b->update([
                'level'                => $target,
                'target_level'         => null,
                'is_constructing'      => false,
                'construction_ends_at' => null,
            ]);

            if ($b->type?->key === 'otag') {
                $village->update(['otag_level' => $target]);
            }

            $stats = BuildingLevel::where('building_type_id', $b->building_type_id)
                ->where('level', $target)->first();

            if ($stats && $stats->xp_reward) {
                $this->progression->addXp($village->player, (int) $stats->xp_reward);
            }
        }

        $village->refresh()->load('buildings.type');
    }

    /** Kullanılan ve toplam işçi (usta) sayısı. */
    public function builders(Village $village): array
    {
        $busy = $village->buildings()->where('is_constructing', true)->count();

        return ['busy' => $busy, 'total' => (int) $village->builders, 'free' => max(0, $village->builders - $busy)];
    }

    /** Yeni bina kurar. */
    public function place(Village $village, string $typeKey, int $x, int $y): Building
    {
        $type = BuildingType::where('key', $typeKey)->firstOr(
            fn () => throw GameException::bulunamadi("Bina tipi bulunamadı: $typeKey")
        );

        $this->settle($village);
        $this->resources->sync($village);

        $allowed = $type->allowedCount($village->otag_level);
        $existing = $village->buildings()->where('building_type_id', $type->id)->count();

        if ($allowed <= 0) {
            throw new GameException("{$type->name} için Otağ seviyen yetersiz.", 'otag_seviyesi');
        }
        if ($existing >= $allowed) {
            throw new GameException("Bu Otağ seviyesinde en fazla {$allowed} adet {$type->name} kurabilirsin.", 'adet_siniri');
        }

        $builders = $this->builders($village);
        if ($builders['free'] <= 0) {
            throw new GameException('Bütün ustalar meşgul.', 'usta_yok');
        }

        $this->assertFits($village, $type, $x, $y, null);

        $stats = BuildingLevel::where('building_type_id', $type->id)->where('level', 1)->firstOr(
            fn () => throw GameException::bulunamadi('Bina seviyesi tanımsız.')
        );

        return DB::transaction(function () use ($village, $type, $x, $y, $stats) {
            $this->resources->spend($village, $stats->costs());

            // Duvar ve tuzaklar anında kurulur (usta harcamaz).
            $instant = in_array($type->category, ['duvar', 'tuzak'], true) || $stats->build_seconds === 0;

            return Building::create([
                'village_id'           => $village->id,
                'building_type_id'     => $type->id,
                'level'                => $instant ? 1 : 0,
                'x'                    => $x,
                'y'                    => $y,
                'is_constructing'      => ! $instant,
                'target_level'         => $instant ? null : 1,
                'construction_ends_at' => $instant ? null : now()->addSeconds($stats->build_seconds),
                'collected_at'         => now(),
            ]);
        });
    }

    /** Binayı bir üst seviyeye çıkarır. */
    public function upgrade(Village $village, Building $building): Building
    {
        $this->settle($village);
        $this->resources->sync($village);
        $building->refresh();

        if ($building->is_constructing) {
            throw new GameException('Bu bina hâlâ inşa hâlinde.', 'insaat_suruyor');
        }

        $type = $building->type;
        $next = $building->level + 1;

        if ($next > $type->max_level) {
            throw new GameException("{$type->name} azami seviyede.", 'azami_seviye');
        }

        $stats = BuildingLevel::where('building_type_id', $type->id)->where('level', $next)->firstOr(
            fn () => throw GameException::bulunamadi('Seviye tanımsız.')
        );

        // Otağ dışındaki binalar Otağ seviyesini geçemez.
        if ($type->key !== 'otag' && $stats->requires_otag_level > $village->otag_level) {
            throw new GameException(
                "Bu seviye için Otağ {$stats->requires_otag_level}. seviyede olmalı.",
                'otag_seviyesi'
            );
        }

        $builders = $this->builders($village);
        if ($builders['free'] <= 0) {
            throw new GameException('Bütün ustalar meşgul.', 'usta_yok');
        }

        return DB::transaction(function () use ($village, $building, $stats, $next) {
            $this->resources->spend($village, $stats->costs());

            $instant = $stats->build_seconds === 0;
            $building->update([
                'level'                => $instant ? $next : $building->level,
                'target_level'         => $next,
                'is_constructing'      => ! $instant,
                'construction_ends_at' => $instant ? null : now()->addSeconds($stats->build_seconds),
            ]);

            if ($instant) {
                $building->update(['target_level' => null]);
                if ($building->type->key === 'otag') {
                    $village->update(['otag_level' => $next]);
                }
            }

            return $building->refresh();
        });
    }

    /** İnşaatı iptal eder, harcanan kaynağın yarısını geri verir. */
    public function cancel(Village $village, Building $building): void
    {
        if (! $building->is_constructing) {
            throw new GameException('İptal edilecek inşaat yok.', 'insaat_yok');
        }

        $target = $building->target_level ?? 1;
        $stats = BuildingLevel::where('building_type_id', $building->building_type_id)
            ->where('level', $target)->first();

        DB::transaction(function () use ($village, $building, $stats) {
            if ($stats) {
                $refund = array_map(fn ($v) => (int) floor($v / 2), $stats->costs());
                $this->resources->add($village, $refund);
            }

            if ($building->level < 1) {
                $building->delete(); // yeni kurulan bina tamamen kalkar
            } else {
                $building->update([
                    'is_constructing'      => false,
                    'target_level'         => null,
                    'construction_ends_at' => null,
                ]);
            }
        });
    }

    /** Binayı başka bir kareye taşır (bedava, savaş düzeni için). */
    public function move(Village $village, Building $building, int $x, int $y): Building
    {
        $this->assertFits($village, $building->type, $x, $y, $building->id);
        $building->update(['x' => $x, 'y' => $y]);

        return $building;
    }

    public function destroy(Village $village, Building $building): void
    {
        if ($building->type->key === 'otag') {
            throw GameException::izinYok('Otağ yıkılamaz.');
        }
        if (! in_array($building->type->category, ['duvar', 'tuzak', 'sus'], true)) {
            throw GameException::izinYok('Yalnızca duvar, tuzak ve süsler kaldırılabilir.');
        }

        $building->delete();
    }

    /** Gök Taşı ile inşaatı anında bitirir. */
    public function finishInstantly(Village $village, Building $building): Building
    {
        if (! $building->is_constructing) {
            throw new GameException('Bitirilecek inşaat yok.', 'insaat_yok');
        }

        $seconds = $building->remainingSeconds();
        $cost = $this->progression->instantCost($seconds);
        $player = $village->player;

        if ($player->gok_tasi < $cost) {
            throw new GameException("Bu inşaatı bitirmek için {$cost} Gök Taşı gerekir.", 'gok_tasi_yetersiz');
        }

        DB::transaction(function () use ($player, $cost, $building, $village) {
            $player->decrement('gok_tasi', $cost);
            $building->update(['construction_ends_at' => now()->subSecond()]);
            $this->settle($village);
        });

        return $building->refresh();
    }

    /**
     * Binanın ızgaraya sığdığını ve başka binayla çakışmadığını doğrular.
     */
    private function assertFits(Village $village, BuildingType $type, int $x, int $y, ?int $ignoreId): void
    {
        $size = config('game.grid.size');
        $margin = config('game.grid.margin');

        if ($x < $margin || $y < $margin
            || $x + $type->grid_w > $size - $margin
            || $y + $type->grid_h > $size - $margin) {
            throw new GameException('Bina oba sınırlarının dışına taşıyor.', 'izgara_disi');
        }

        $others = $village->buildings()->with('type')->when(
            $ignoreId,
            fn ($q) => $q->where('id', '!=', $ignoreId)
        )->get();

        foreach ($others as $o) {
            $ow = $o->type->grid_w;
            $oh = $o->type->grid_h;
            $overlapX = $x < $o->x + $ow && $x + $type->grid_w > $o->x;
            $overlapY = $y < $o->y + $oh && $y + $type->grid_h > $o->y;
            if ($overlapX && $overlapY) {
                throw new GameException('Burada başka bir bina var.', 'kare_dolu');
            }
        }
    }
}
