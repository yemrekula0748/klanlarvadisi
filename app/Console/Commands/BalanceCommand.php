<?php

namespace App\Console\Commands;

use App\Models\BuildingType;
use App\Models\SpellType;
use App\Models\TroopType;
use App\Models\User;
use App\Models\Village;
use App\Services\BattleService;
use App\Services\Combat\BattleSimulator;
use App\Services\LayoutGenerator;
use App\Services\VillageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Denge test aracı.
 *
 * Her Otağ seviyesi için üretilmiş bir obaya, o seviyede gerçekten
 * kurulabilecek birkaç farklı ORDU TİPİ ile, birkaç farklı taktikle
 * saldırır; en iyi sonucu veren ordu tipini bildirir.
 *
 * Asker seviyeleri Demirci (laboratuvar) seviyesiyle sınırlıdır — gerçek
 * oyuncu ilerlemesinin aynısı. Veritabanına kalıcı bir şey yazılmaz.
 *
 * Hedef: eşit seviyede, doğru ordu tipiyle yapılan saldırı ortalama
 * 1.5-2.5 yıldız (%55-85 yıkım) almalı.
 *
 *   php artisan game:balance
 *   php artisan game:balance --levels=11,13 --runs=3 --detail
 */
class BalanceCommand extends Command
{
    protected $signature = 'game:balance
        {--levels= : Denenecek Otağ seviyeleri (örn. 5,8,11)}
        {--runs=2 : Her ordu tipi için taktik sayısı}
        {--detail : Her ordu tipinin ayrı sonucunu yazdır}';

    protected $description = 'Saldırı/savunma dengesini simüle ederek ölçer';

    /**
     * Ordu tipleri. `share` = ordugâh kapasitesinden alınan pay.
     * Erişilemeyen askerlerin payı diğerlerine dağıtılır.
     */
    private const COMPS = [
        'Yaya Ordusu' => [
            'troops' => ['gurzcu' => 0.18, 'tepegoz' => 0.30, 'ceri' => 0.22,
                'okcu_kiz' => 0.18, 'alp' => 0.08, 'baksi' => 0.04],
            'spells' => ['sifa' => 2, 'gazap' => 1, 'yildirim' => 1],
        ],
        'Hava Ordusu' => [
            'troops' => ['konrul' => 0.55, 'togrul' => 0.30, 'zumrudu_anka' => 0.15],
            'spells' => ['sifa' => 3, 'gazap' => 1],
        ],
        'Kara Ordu' => [
            'troops' => ['karakoncolos' => 0.25, 'albasti' => 0.25, 'sahmeran' => 0.25,
                'erlik_comezi' => 0.15, 'gurzcu' => 0.10],
            'spells' => ['zehir' => 2, 'golge' => 1, 'karakis' => 1],
        ],
        'Kuşatma Ordusu' => [
            'troops' => ['mancinik_arabasi' => 0.25, 'tepegoz' => 0.25, 'alp' => 0.25,
                'gurzcu' => 0.15, 'baksi' => 0.10],
            'spells' => ['sifa' => 2, 'gazap' => 2, 'karakis' => 1],
        ],
    ];

    /** @var array<string,int> asker anahtarı => gereken asgari Otağ seviyesi */
    private array $unlockCache = [];

    public function handle(LayoutGenerator $layout, VillageService $villages, BattleService $battles): int
    {
        $levels = $this->option('levels')
            ? array_map('intval', explode(',', $this->option('levels')))
            : [3, 5, 7, 9, 11, 13];
        $runs = max(1, (int) $this->option('runs'));

        $rows = [];
        $detail = [];

        DB::beginTransaction();

        try {
            $user = User::create([
                'name'     => 'DengeTesti',
                'email'    => 'denge-'.uniqid().'@test.local',
                'password' => 'sifre1234',
            ]);
            $player = $villages->createForUser($user, 'Denge'.random_int(10000, 99999));
            $village = $player->village;

            foreach ($levels as $th) {
                $layout->build($village, $th);
                $village->refresh()->load('buildings.type');

                $defense = $battles->buildDefense($village)['buildings'];
                $capacity = $this->capacityFor($th);
                $labLevel = $this->labLevelFor($th);

                $best = null;

                foreach (self::COMPS as $compName => $comp) {
                    $army = $this->armyFor($comp, $th, $capacity, $labLevel);
                    if (count($army['units']) < 3) {
                        continue; // bu seviyede kurulamıyor
                    }

                    $stars = [];
                    $destruction = [];
                    $durations = [];

                    for ($r = 0; $r < $runs; $r++) {
                        $result = (new BattleSimulator)->run(
                            ['buildings' => $defense, 'clan_troops' => []],
                            [
                                'units'  => $this->deploy($army['units'], $r),
                                'spells' => $army['spells'],
                            ],
                            1000 + $r * 31
                        );
                        $stars[] = $result['stars'];
                        $destruction[] = $result['destruction'];
                        $durations[] = $result['duration'];
                    }

                    $avgStars = array_sum($stars) / count($stars);
                    $avgDestruction = array_sum($destruction) / count($destruction);

                    $detail[] = [
                        "Otağ $th", $compName, count($army['units']),
                        round($avgStars, 2), '%'.round($avgDestruction, 1),
                        round(array_sum($durations) / count($durations)).'s',
                    ];

                    if (! $best || $avgDestruction > $best['destruction']) {
                        $best = [
                            'comp'        => $compName,
                            'stars'       => $avgStars,
                            'destruction' => $avgDestruction,
                            'duration'    => array_sum($durations) / count($durations),
                            'units'       => count($army['units']),
                        ];
                    }
                }

                if (! $best) {
                    continue;
                }

                $rows[] = [
                    "Otağ $th",
                    count($defense),
                    count(array_filter($defense, fn ($b) => $b['category'] === 'savunma')),
                    "sv$labLevel",
                    $capacity,
                    $best['comp'],
                    round($best['stars'], 2),
                    '%'.round($best['destruction'], 1),
                    $this->verdict($best['destruction']),
                ];
            }
        } finally {
            DB::rollBack();
        }

        if ($this->option('detail')) {
            $this->newLine();
            $this->table(['Seviye', 'Ordu Tipi', 'Asker', 'Yıldız', 'Yıkım', 'Süre'], $detail);
        }

        $this->newLine();
        $this->table(
            ['Seviye', 'Bina', 'Savunma', 'Demirci', 'Alan', 'En İyi Ordu', 'Yıldız', 'Yıkım', 'Değerlendirme'],
            $rows
        );
        $this->line('Hedef: en iyi ordu tipiyle ortalama 1.5-2.5 yıldız, %55-85 yıkım.');

        return self::SUCCESS;
    }

    private function verdict(float $destruction): string
    {
        return match (true) {
            $destruction < 35  => 'savunma çok güçlü',
            $destruction < 55  => 'savunma güçlü',
            $destruction <= 88 => 'DENGELİ',
            $destruction < 96  => 'saldırı güçlü',
            default            => 'saldırı çok güçlü',
        };
    }

    /** O Otağ seviyesindeki toplam ordugâh kapasitesi. */
    private function capacityFor(int $th): int
    {
        $type = BuildingType::with('levels')->where('key', 'ordugah')->first();
        $count = $type->allowedCount($th);
        $best = $this->bestLevel($type, $th);
        $stats = $type->levels->firstWhere('level', $best);

        return $count * (int) $stats->troop_capacity;
    }

    /** Asker seviyelerini sınırlayan Demirci seviyesi. */
    private function labLevelFor(int $th): int
    {
        $demirci = BuildingType::with('levels')->where('key', 'demirci')->first();
        if (! $demirci || $demirci->allowedCount($th) < 1) {
            return 1;
        }

        return $this->bestLevel($demirci, $th);
    }

    private function bestLevel(BuildingType $type, int $th): int
    {
        $best = 1;
        foreach ($type->levels as $lv) {
            if ($lv->requires_otag_level <= $th) {
                $best = $lv->level;
            }
        }

        return $best;
    }

    /**
     * Bir askerin açıldığı asgari Otağ seviyesi — kataloğun kendisinden
     * türetilir (Kışla / Kara Kışla seviyesi zinciri üzerinden).
     */
    private function unlockThFor(TroopType $type): int
    {
        if (isset($this->unlockCache[$type->key])) {
            return $this->unlockCache[$type->key];
        }

        $barracks = $type->category === 'karanlik' ? 'kara_kisla' : 'kisla';
        $b = BuildingType::with('levels')->where('key', $barracks)->first();
        if (! $b) {
            return 99;
        }

        $need = (int) $type->unlock_kisla_level;
        $level = $b->levels->firstWhere('level', $need);
        if (! $level) {
            return 99;
        }

        // Kışlanın o seviyesi için gereken Otağ seviyesi + kışlanın kendisinin
        // kurulabildiği Otağ seviyesi.
        for ($th = 1; $th <= 15; $th++) {
            if ($b->allowedCount($th) >= 1 && $level->requires_otag_level <= $th) {
                return $this->unlockCache[$type->key] = $th;
            }
        }

        return $this->unlockCache[$type->key] = 99;
    }

    /** Ordu tipini, kapasiteye ve Demirci seviyesine göre kurar. */
    private function armyFor(array $comp, int $th, int $capacity, int $labLevel): array
    {
        $types = TroopType::with('levels')->get()->keyBy('key');

        // Erişilebilen askerleri katalogdan belirle ve payları yeniden dağıt.
        $plan = [];
        foreach ($comp['troops'] as $key => $share) {
            $type = $types[$key] ?? null;
            if ($type && $th >= $this->unlockThFor($type)) {
                $plan[$key] = $share;
            }
        }
        // Tek bir uzman birime (örn. yalnızca Gürzcü) bütün kapasiteyi
        // vermek gerçekçi değil: gerçek oyuncu karma ordu kurar.
        if (count($plan) < 2) {
            return ['units' => [], 'spells' => []];
        }

        foreach ($plan as $key => $share) {
            $plan[$key] = min($share * 2.5, $comp['troops'][$key] * 2.5);
        }

        $sum = array_sum($plan);
        $units = [];

        foreach ($plan as $key => $share) {
            $type = $types[$key];
            $count = intdiv((int) floor($capacity * ($share / $sum)), $type->housing);
            if ($count === 0) {
                continue;
            }

            $level = max(1, min($type->max_level, $labLevel));
            $stats = $type->statsAt($level);
            if (! $stats) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                $units[] = [
                    'key'          => $key,
                    'level'        => $level,
                    'hp'           => $stats->hitpoints,
                    'dps'          => $stats->damage_per_second,
                    'speed'        => $type->move_speed / 10,
                    'range'        => $type->attack_range,
                    'attack_speed' => $type->attack_speed,
                    'pref'         => $type->preferred_target,
                    'mult'         => $type->target_damage_multiplier,
                    'air'          => $type->is_air,
                    'splash'       => $type->splash_radius,
                ];
            }
        }

        return ['units' => $units, 'spells' => $this->spellsFor($comp['spells'] ?? [], $th, $labLevel)];
    }

    /** Tılsımları obanın merkezine, savaşın ortasında kullanır. */
    private function spellsFor(array $wanted, int $th, int $labLevel): array
    {
        $atolye = BuildingType::with('levels')->where('key', 'buyu_atolyesi')->first();
        if (! $atolye || $atolye->allowedCount($th) < 1) {
            return [];
        }

        $center = intdiv((int) config('game.grid.size'), 2);
        $tickRate = (int) config('game.battle.tick_rate');
        $types = SpellType::with('levels')->get()->keyBy('key');
        $out = [];
        $t = 22;

        foreach ($wanted as $key => $count) {
            $type = $types[$key] ?? null;
            if (! $type || $type->unlock_buyu_level > $this->bestLevel($atolye, $th)) {
                continue;
            }

            $level = max(1, min($type->max_level, max(1, intdiv($labLevel, 2))));
            $stats = $type->statsAt($level);

            for ($i = 0; $i < $count; $i++) {
                $out[] = [
                    'key'    => $key,
                    'level'  => $level,
                    'x'      => $center + ($i % 2 ? 3 : -3),
                    'y'      => $center - 4,
                    'tick'   => (int) round($t * $tickRate),
                    'radius' => $type->radius,
                    'effect' => $stats?->effect ?? [],
                ];
                $t += 4;
            }
        }

        return $out;
    }

    /**
     * Taktik deseni: 0 = tek kenara yoğun, 1 = iki kenardan kıskaç,
     * 2 = köşeden dalga. Duvar kırıcılar ve etler her zaman önce iner.
     */
    private function deploy(array $units, int $pattern): array
    {
        $grid = (int) config('game.grid.size');
        $tickRate = (int) config('game.battle.tick_rate');

        $order = ['duvar' => 0, 'savunma' => 1, 'yok' => 2, 'kaynak' => 2, 'otag' => 2];
        usort($units, fn ($a, $b) => ($order[$a['pref']] ?? 2) <=> ($order[$b['pref']] ?? 2));

        $out = [];
        foreach ($units as $i => $u) {
            [$x, $y] = match ($pattern % 3) {
                0 => [18 + ($i % 9), 3],
                1 => $i % 2 === 0 ? [18 + ($i % 8), 3] : [18 + ($i % 8), $grid - 4],
                default => [3 + ($i % 7), 6 + ($i % 9)],
            };

            $u['x'] = (float) $x;
            $u['y'] = (float) $y;
            $u['deploy_tick'] = (int) round($i * 0.12 * $tickRate);
            $out[] = $u;
        }

        return $out;
    }
}
