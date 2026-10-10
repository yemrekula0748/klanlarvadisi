<?php

namespace App\Services\Combat;

/**
 * Sunucu tarafı, tamamen deterministik savaş simülasyonu.
 *
 * Unity istemcisi savaşı HESAPLAMAZ; yalnızca burada üretilen `replay`
 * zaman çizgisini oynatır. Böylece hile imkânsızdır ve her oyuncu aynı
 * savaşı aynı şekilde izler.
 *
 * Çalışma mantığı:
 *  - 44x44 ızgara, saniyede 10 adım (tick), en çok 180 saniye.
 *  - Yer askerleri A* ile yol bulur; duvarlar yüksek maliyetlidir, bu yüzden
 *    açık varsa dolaşır, yoksa duvarı kırar.
 *  - Savunma kuleleri menzilindeki en yakın uygun hedefi vurur.
 *  - Tuzaklar, Boy Otağı askerleri ve Kurt İni kurtları tetiklenir.
 *  - Tılsımlar (büyüler) belirtilen anda ve konumda etki eder.
 */
class BattleSimulator
{
    private int $tickRate;
    private float $dt;
    private int $maxTicks;
    private int $gridSize;

    private array $buildings = [];
    private array $units = [];
    private array $events = [];
    private array $spells = [];
    private array $effects = [];      // etkin alan etkileri (gazap, şifa, dondurma)
    private array $cellMap = [];      // ızgara hücresi => bina indeksi
    private array $wallMap = [];      // ızgara hücresi => duvar bina indeksi
    private PathFinder $path;
    private Rng $rng;
    private int $tick = 0;
    private int $nextUnitId = 1;
    private array $lastHp = [];
    private bool $clanCastleTriggered = false;
    private int $aliveDefenders = 0;
    private int $aliveAttackers = 0;
    private int $pendingDeploys = 0;
    private bool $hasUnfiredTrap = true;

    public function __construct()
    {
        $this->tickRate = (int) config('game.battle.tick_rate');
        $this->dt = 1.0 / $this->tickRate;
        $this->maxTicks = (int) config('game.battle.max_duration') * $this->tickRate;
        $this->gridSize = (int) config('game.grid.size');
    }

    /**
     * @param  array  $defense  ['buildings' => [...], 'clan_troops' => [...]]
     * @param  array  $army     ['units' => [...], 'spells' => [...]]
     */
    public function run(array $defense, array $army, int $seed): array
    {
        $this->rng = new Rng($seed);
        $this->loadBuildings($defense['buildings'] ?? []);
        $this->buildGrid();
        $this->loadUnits($army['units'] ?? []);
        $this->spells = $army['spells'] ?? [];
        $this->clanTroops = $defense['clan_troops'] ?? [];

        $totalCounting = $this->countingBuildings();

        for ($this->tick = 0; $this->tick <= $this->maxTicks; $this->tick++) {
            $this->deployUnits();
            $this->castSpells();
            $this->expireEffects();
            $this->stepUnits();
            $this->stepDefenses();
            $this->checkTraps();
            $this->checkClanCastle();

            if ($this->tick % $this->tickRate === 0) {
                $this->syncHp();
            }

            if ($this->destroyedCount() >= $totalCounting) {
                break; // %100
            }
            if ($this->tick > 0 && ! $this->anyAttackerLeft()) {
                break; // bütün ordu yok oldu
            }
        }

        $this->syncHp();

        $destruction = $totalCounting > 0
            ? round(($this->destroyedCount() / $totalCounting) * 100, 2)
            : 0.0;

        $otagDown = $this->isOtagDestroyed();
        $stars = 0;
        if ($destruction >= config('game.battle.star_destruction')) {
            $stars++;
        }
        if ($otagDown) {
            $stars++;
        }
        if ($destruction >= 100) {
            $stars = 3;
        }

        $duration = (int) ceil($this->tick / $this->tickRate);

        return [
            'stars'       => min(3, $stars),
            'destruction' => min(100.0, $destruction),
            'duration'    => $duration,
            'otag_down'   => $otagDown,
            'replay'      => [
                'version'        => 1,
                'seed'           => $seed,
                'grid'           => $this->gridSize,
                'tick_rate'      => $this->tickRate,
                'duration_ticks' => $this->tick,
                'buildings'      => array_map(fn ($b) => [
                    'id'  => $b['id'],
                    'key' => $b['key'],
                    'lv'  => $b['level'],
                    'x'   => $b['gx'],
                    'y'   => $b['gy'],
                    'w'   => $b['w'],
                    'h'   => $b['h'],
                    'hp'  => (int) $b['max_hp'],
                    'cat' => $b['category'],
                    'as'  => round($b['attack_speed'], 2),
                    'rng' => round($b['range'], 2),
                ], $this->buildings),
                'units'          => array_map(fn ($u) => [
                    'id'   => $u['id'],
                    'key'  => $u['key'],
                    'lv'   => $u['level'],
                    'hp'   => (int) $u['max_hp'],
                    'air'  => $u['air'],
                    'side' => $u['side'],
                    'hero' => $u['is_hero'],
                    'as'   => round($u['attack_speed'], 2),
                    'spd'  => round($u['speed'], 2),
                ], $this->units),
                'events'         => $this->events,
                'result'         => [
                    'stars'       => min(3, $stars),
                    'destruction' => min(100.0, $destruction),
                    'duration'    => $duration,
                ],
            ],
        ];
    }

    private array $clanTroops = [];

    /* ------------------------------------------------------------------ */
    /* Kurulum                                                             */
    /* ------------------------------------------------------------------ */

    private function loadBuildings(array $list): void
    {
        foreach ($list as $b) {
            $w = (int) ($b['w'] ?? 3);
            $h = (int) ($b['h'] ?? 3);

            $this->buildings[] = [
                'id'             => (int) $b['id'],
                'key'            => (string) $b['key'],
                'category'       => (string) ($b['category'] ?? 'ozel'),
                'level'          => (int) ($b['level'] ?? 1),
                'gx'             => (int) $b['x'],
                'gy'             => (int) $b['y'],
                'w'              => $w,
                'h'              => $h,
                'cx'             => $b['x'] + $w / 2,
                'cy'             => $b['y'] + $h / 2,
                'hp'             => (float) ($b['hp'] ?? 100),
                'max_hp'         => (float) ($b['hp'] ?? 100),
                'dps'            => (float) ($b['dps'] ?? 0),
                'range'          => (float) ($b['range'] ?? 0),
                'targets_air'    => (bool) ($b['targets_air'] ?? false),
                'targets_ground' => (bool) ($b['targets_ground'] ?? true),
                'splash'         => (float) ($b['splash'] ?? 0),
                'attack_speed'   => (float) ($b['attack_speed'] ?? 1.0),
                'min_range'      => (float) ($b['min_range'] ?? 0),
                'alive'          => true,
                'cooldown'       => 0.0,
                'frozen_until'   => -1,
                'target'         => null,
                'is_trap'        => (bool) ($b['is_trap'] ?? false),
                'trap_radius'    => (float) ($b['trap_radius'] ?? 0),
                'trap_damage'    => (float) ($b['trap_damage'] ?? 0),
                'trap_air'       => (bool) ($b['trap_air'] ?? false),
                'trap_fired'     => false,
                'counts'         => ! in_array($b['category'] ?? '', ['duvar', 'tuzak'], true),
                'spawn'          => $b['spawn'] ?? null,
                'is_clan_castle' => ($b['key'] ?? '') === 'boy_otagi',
                'clan_radius'    => (float) ($b['clan_radius'] ?? 0),
            ];
        }
    }

    private function buildGrid(): void
    {
        $cells = [];
        foreach ($this->buildings as $i => $b) {
            if ($b['is_trap']) {
                continue; // tuzaklar yol kapatmaz
            }
            $code = $b['category'] === 'duvar' ? 2 : 1;
            for ($y = $b['gy']; $y < $b['gy'] + $b['h']; $y++) {
                for ($x = $b['gx']; $x < $b['gx'] + $b['w']; $x++) {
                    $idx = $y * $this->gridSize + $x;
                    $cells[$idx] = $code;
                    $this->cellMap[$idx] = $i;
                    if ($code === 2) {
                        $this->wallMap[$idx] = $i;
                    }
                }
            }
        }

        $this->path = new PathFinder($this->gridSize, $cells);
    }

    private function loadUnits(array $list): void
    {
        // Güvenlik sınırı: tek savaşta en çok 350 birim simüle edilir.
        foreach (array_slice($list, 0, 350) as $u) {
            $this->units[] = $this->makeUnit($u, 'saldiran');
            $this->aliveAttackers++;
            $this->pendingDeploys++;
        }
    }

    private function makeUnit(array $u, string $side): array
    {
        return [
            'id'            => $this->nextUnitId++,
            'key'           => (string) $u['key'],
            'level'         => (int) ($u['level'] ?? 1),
            'hp'            => (float) $u['hp'],
            'max_hp'        => (float) $u['hp'],
            'dps'           => (float) $u['dps'],
            'speed'         => (float) ($u['speed'] ?? 1.6),
            'range'         => (float) ($u['range'] ?? 0.6),
            'attack_speed'  => max(0.2, (float) ($u['attack_speed'] ?? 1.0)),
            'pref'          => (string) ($u['pref'] ?? 'yok'),
            'mult'          => (float) ($u['mult'] ?? 1.0),
            'air'           => (bool) ($u['air'] ?? false),
            'splash'        => (float) ($u['splash'] ?? 0),
            'x'             => (float) ($u['x'] ?? 0),
            'y'             => (float) ($u['y'] ?? 0),
            'deploy_tick'   => (int) ($u['deploy_tick'] ?? 0),
            'deployed'      => false,
            'alive'         => true,
            'side'          => $side,
            'is_hero'       => (bool) ($u['is_hero'] ?? false),
            'ability'       => $u['ability'] ?? null,
            'ability_used'  => false,
            'target'        => null,
            'target_unit'   => null,
            'path'          => [],
            'path_idx'      => 0,
            'cooldown'      => 0.0,
            'repath_tick'   => -999,
            'last_atk'      => null,
            'last_atk_unit' => null,
            'rage_until'    => -1,
            'heal_until'    => -1,
            'heal_rate'     => 0.0,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Tick adımları                                                       */
    /* ------------------------------------------------------------------ */

    private function deployUnits(): void
    {
        if ($this->pendingDeploys <= 0) {
            return;
        }

        foreach ($this->units as $i => $u) {
            if ($u['deployed'] || ! $u['alive'] || $u['deploy_tick'] > $this->tick) {
                continue;
            }
            $this->units[$i]['deployed'] = true;
            $this->pendingDeploys--;
            $this->events[] = [
                't' => $this->tick, 'e' => 'dep', 'u' => $u['id'],
                'x' => round($u['x'], 2), 'y' => round($u['y'], 2),
            ];
        }
    }

    private function castSpells(): void
    {
        foreach ($this->spells as $k => $s) {
            if (($s['tick'] ?? 0) > $this->tick || ($s['cast'] ?? false)) {
                continue;
            }
            $this->spells[$k]['cast'] = true;

            $effect = $s['effect'] ?? [];
            $radius = (float) ($s['radius'] ?? 3);
            $x = (float) $s['x'];
            $y = (float) $s['y'];

            $this->events[] = [
                't' => $this->tick, 'e' => 'spell', 'k' => $s['key'],
                'x' => round($x, 2), 'y' => round($y, 2),
                'r' => $radius, 'lv' => (int) ($s['level'] ?? 1),
            ];

            // Anında hasar (Yıldırım Tılsımı)
            if (! empty($effect['damage'])) {
                foreach ($this->buildings as $i => $b) {
                    if (! $b['alive'] || $b['is_trap']) {
                        continue;
                    }
                    if ($this->distToBuilding($x, $y, $b) <= $radius) {
                        $this->damageBuilding($i, (float) $effect['damage']);
                    }
                }
                foreach ($this->units as $i => $u) {
                    if ($u['alive'] && $u['deployed'] && $u['side'] === 'savunan'
                        && $this->dist($x, $y, $u['x'], $u['y']) <= $radius) {
                        $this->damageUnit($i, (float) $effect['damage']);
                    }
                }
            }

            // Süreli alan etkileri
            if (! empty($effect['duration'])) {
                $this->effects[] = [
                    'key'   => $s['key'],
                    'x'     => $x,
                    'y'     => $y,
                    'r'     => $radius,
                    'until' => $this->tick + (int) ($effect['duration'] * $this->tickRate),
                    'heal'  => (float) ($effect['heal_per_second'] ?? 0),
                    'rage'  => (float) ($effect['speed_bonus'] ?? 0),
                    'dmg'   => (float) ($effect['damage_bonus'] ?? 0),
                    'freeze' => (bool) ($effect['freeze'] ?? false),
                ];
            }

            // Çağrı tılsımı: asker doğurur
            if (! empty($effect['summon'])) {
                for ($n = 0; $n < (int) $effect['summon_count']; $n++) {
                    $unit = $this->makeUnit([
                        'key'   => $effect['summon'],
                        'level' => (int) ($s['level'] ?? 1),
                        'hp'    => (float) $effect['summon_hp'],
                        'dps'   => (float) $effect['summon_dps'],
                        'speed' => 1.6,
                        'range' => 0.6,
                        'x'     => $x + $this->rng->jitter(1.2),
                        'y'     => $y + $this->rng->jitter(1.2),
                        'deploy_tick' => $this->tick,
                    ], 'saldiran');
                    $unit['deployed'] = true;
                    $this->units[] = $unit;
                    $this->aliveAttackers++;
                    $this->events[] = [
                        't' => $this->tick, 'e' => 'spawn', 'u' => $unit['id'],
                        'key' => $unit['key'], 'lv' => $unit['level'],
                        'hp' => (int) $unit['max_hp'], 'air' => false,
                        'side' => 'saldiran',
                        'x' => round($unit['x'], 2), 'y' => round($unit['y'], 2),
                    ];
                }
            }
        }
    }

    private function expireEffects(): void
    {
        $this->effects = array_values(array_filter(
            $this->effects,
            fn ($e) => $e['until'] > $this->tick
        ));

        foreach ($this->buildings as $i => $b) {
            if ($b['frozen_until'] >= 0 && $b['frozen_until'] <= $this->tick) {
                $this->buildings[$i]['frozen_until'] = -1;
            }
        }

        foreach ($this->effects as $e) {
            if ($e['freeze']) {
                foreach ($this->buildings as $i => $b) {
                    if ($b['alive'] && $b['dps'] > 0
                        && $this->distToBuilding($e['x'], $e['y'], $b) <= $e['r']) {
                        $this->buildings[$i]['frozen_until'] = $e['until'];
                    }
                }
            }
        }
    }

    private function stepUnits(): void
    {
        foreach ($this->units as $i => $u) {
            if (! $u['alive'] || ! $u['deployed']) {
                continue;
            }

            // Alan etkileri (şifa / gazap)
            $speedMul = 1.0;
            $dmgMul = 1.0;
            foreach ($this->effects as $e) {
                if ($u['side'] !== 'saldiran') {
                    continue;
                }
                if ($this->dist($e['x'], $e['y'], $u['x'], $u['y']) > $e['r']) {
                    continue;
                }
                if ($e['heal'] > 0) {
                    $this->units[$i]['hp'] = min($u['max_hp'], $u['hp'] + $e['heal'] * $this->dt);
                }
                $speedMul = max($speedMul, 1.0 + $e['rage']);
                $dmgMul = max($dmgMul, 1.0 + $e['dmg']);
            }
            $u = $this->units[$i];

            // Kahraman yeteneği: canı %50'nin altına inince tetiklenir.
            if ($u['is_hero'] && ! $u['ability_used'] && $u['ability']
                && $u['hp'] <= $u['max_hp'] * 0.5) {
                $this->units[$i]['ability_used'] = true;
                $this->applyHeroAbility($i);
                $u = $this->units[$i];
            }

            $this->units[$i]['cooldown'] = max(0, $u['cooldown'] - $this->dt);
            $u = $this->units[$i];

            if ($u['side'] === 'savunan') {
                $this->stepDefenderUnit($i, $dmgMul);

                continue;
            }

            // Yakında savunan asker varsa önce onunla uğraş. Savaşın büyük
            // bölümünde hiç savunan asker olmadığı için bu tarama tamamen
            // atlanır; aksi hâlde her adımda O(asker²) maliyet doğar.
            if ($this->aliveDefenders > 0) {
                $threat = $this->nearestEnemyUnit($i, 'savunan', $u['range'] + 1.5);
                if ($threat !== null) {
                    $this->attackUnit($i, $threat, $dmgMul);

                    continue;
                }
            }

            // Hedef bina seç
            if ($u['target'] === null || ! ($this->buildings[$u['target']]['alive'] ?? false)) {
                $this->selectBuildingTarget($i);
                $u = $this->units[$i];
                if ($u['target'] === null) {
                    continue; // yıkılacak bina kalmadı
                }
            }

            $b = $this->buildings[$u['target']];
            $d = $this->distToBuilding($u['x'], $u['y'], $b);

            if ($d <= $u['range'] + 0.45) {
                $this->attackBuilding($i, $u['target'], $dmgMul);
            } else {
                $this->moveUnit($i, $speedMul);
            }
        }
    }

    private function stepDefenderUnit(int $i, float $dmgMul): void
    {
        $u = $this->units[$i];
        $enemy = $this->nearestEnemyUnit($i, 'saldiran', 999);

        if ($enemy === null) {
            return;
        }

        $e = $this->units[$enemy];
        $d = $this->dist($u['x'], $u['y'], $e['x'], $e['y']);

        if ($d <= $u['range'] + 0.45) {
            $this->attackUnit($i, $enemy, $dmgMul);
        } else {
            // Düşmana doğru düz hat (savunan askerler oba içinde serbest hareket eder)
            $step = $u['speed'] * $this->dt;
            $this->units[$i]['x'] += (($e['x'] - $u['x']) / max(0.001, $d)) * $step;
            $this->units[$i]['y'] += (($e['y'] - $u['y']) / max(0.001, $d)) * $step;

            if ($this->tick - $u['repath_tick'] >= $this->tickRate) {
                $this->units[$i]['repath_tick'] = $this->tick;
                $this->events[] = [
                    't' => $this->tick, 'e' => 'mv', 'u' => $u['id'],
                    'p' => [[round($this->units[$i]['x'], 2), round($this->units[$i]['y'], 2)]],
                ];
            }
        }
    }

    private function applyHeroAbility(int $i): void
    {
        $u = $this->units[$i];
        $ability = $u['ability'];

        $this->events[] = [
            't' => $this->tick, 'e' => 'abil', 'u' => $u['id'],
            'k' => $ability['key'] ?? 'yetenek',
        ];

        if (! empty($ability['heal'])) {
            $this->units[$i]['hp'] = min($u['max_hp'], $u['hp'] + (float) $ability['heal']);
        }
        if (! empty($ability['radius'])) {
            $this->effects[] = [
                'key'    => $ability['key'] ?? 'yetenek',
                'x'      => $u['x'],
                'y'      => $u['y'],
                'r'      => (float) $ability['radius'],
                'until'  => $this->tick + (int) (($ability['duration'] ?? 5) * $this->tickRate),
                'heal'   => (float) ($ability['heal_per_second'] ?? 0),
                'rage'   => (float) ($ability['speed_bonus'] ?? 0),
                'dmg'    => (float) ($ability['damage_bonus'] ?? 0),
                'freeze' => false,
            ];
        }
        if (! empty($ability['summon_count'])) {
            for ($n = 0; $n < (int) $ability['summon_count']; $n++) {
                $unit = $this->makeUnit([
                    'key'   => $ability['summon'] ?? 'boru',
                    'level' => $u['level'],
                    'hp'    => (float) ($ability['summon_hp'] ?? 300),
                    'dps'   => (float) ($ability['summon_dps'] ?? 40),
                    'speed' => 2.4,
                    'x'     => $u['x'] + $this->rng->jitter(1.0),
                    'y'     => $u['y'] + $this->rng->jitter(1.0),
                    'deploy_tick' => $this->tick,
                ], 'saldiran');
                $unit['deployed'] = true;
                $this->units[] = $unit;
                $this->aliveAttackers++;
                $this->events[] = [
                    't' => $this->tick, 'e' => 'spawn', 'u' => $unit['id'],
                    'key' => $unit['key'], 'lv' => $unit['level'],
                    'hp' => (int) $unit['max_hp'], 'air' => false, 'side' => 'saldiran',
                    'x' => round($unit['x'], 2), 'y' => round($unit['y'], 2),
                ];
            }
        }
    }

    private function selectBuildingTarget(int $i): void
    {
        $u = $this->units[$i];
        $pref = $u['pref'];

        $candidates = $this->buildingIndexesFor($pref);
        if (! $candidates) {
            $candidates = $this->buildingIndexesFor('yok');
        }
        if (! $candidates) {
            $this->units[$i]['target'] = null;

            return;
        }

        $best = null;
        $bestD = INF;
        foreach ($candidates as $bi) {
            $d = $this->distToBuilding($u['x'], $u['y'], $this->buildings[$bi]);
            if ($d < $bestD) {
                $bestD = $d;
                $best = $bi;
            }
        }

        $this->units[$i]['target'] = $best;
        $this->units[$i]['path'] = [];
        $this->units[$i]['path_idx'] = 0;
        $this->computePath($i);
    }

    private function buildingIndexesFor(string $pref): array
    {
        $out = [];
        foreach ($this->buildings as $i => $b) {
            if (! $b['alive'] || $b['is_trap']) {
                continue;
            }

            $ok = match ($pref) {
                'duvar'    => $b['category'] === 'duvar',
                'savunma'  => $b['category'] === 'savunma',
                'kaynak'   => in_array($b['category'], ['kaynak', 'depo'], true),
                'otag'     => $b['key'] === 'otag',
                default    => $b['category'] !== 'duvar',
            };

            if ($ok) {
                $out[] = $i;
            }
        }

        return $out;
    }

    private function computePath(int $i): void
    {
        $u = $this->units[$i];
        if ($u['target'] === null) {
            return;
        }

        $b = $this->buildings[$u['target']];

        if ($u['air']) {
            $this->units[$i]['path'] = [[$b['cx'], $b['cy']]];
            $this->units[$i]['path_idx'] = 0;
            $this->events[] = [
                't' => $this->tick, 'e' => 'mv', 'u' => $u['id'],
                'p' => [[round($b['cx'], 2), round($b['cy'], 2)]],
            ];

            return;
        }

        $goals = [];
        for ($y = $b['gy']; $y < $b['gy'] + $b['h']; $y++) {
            for ($x = $b['gx']; $x < $b['gx'] + $b['w']; $x++) {
                $goals[] = [$x, $y];
            }
        }

        $sx = max(0, min($this->gridSize - 1, (int) floor($u['x'])));
        $sy = max(0, min($this->gridSize - 1, (int) floor($u['y'])));
        $cells = $this->path->find($sx, $sy, $goals, 'b'.$b['id']);

        $pathPoints = array_map(fn ($c) => [$c[0] + 0.5, $c[1] + 0.5], $cells);
        $this->units[$i]['path'] = $pathPoints;
        $this->units[$i]['path_idx'] = 0;
        $this->units[$i]['repath_tick'] = $this->tick;

        if ($pathPoints) {
            $this->events[] = [
                't' => $this->tick, 'e' => 'mv', 'u' => $u['id'],
                'p' => array_map(fn ($p) => [round($p[0], 2), round($p[1], 2)], $pathPoints),
            ];
        }
    }

    private function moveUnit(int $i, float $speedMul): void
    {
        $u = $this->units[$i];
        $remaining = $u['speed'] * $speedMul * $this->dt;

        if (! $u['path'] || $u['path_idx'] >= count($u['path'])) {
            $this->computePath($i);
            $u = $this->units[$i];
            if (! $u['path']) {
                // Yol yok: hedefe doğru düz git (sıkışmayı önler)
                $b = $this->buildings[$u['target']];
                $d = max(0.001, $this->dist($u['x'], $u['y'], $b['cx'], $b['cy']));
                $this->units[$i]['x'] += (($b['cx'] - $u['x']) / $d) * $remaining;
                $this->units[$i]['y'] += (($b['cy'] - $u['y']) / $d) * $remaining;

                return;
            }
        }

        while ($remaining > 0.0001 && $u['path_idx'] < count($u['path'])) {
            [$wx, $wy] = $u['path'][$u['path_idx']];

            // Yer askeri: sıradaki hücre duvarsa duvarı kır.
            if (! $u['air']) {
                $cx = (int) floor($wx);
                $cy = (int) floor($wy);
                $cellIdx = $cy * $this->gridSize + $cx;
                $wallIdx = $this->wallMap[$cellIdx] ?? null;
                if ($wallIdx !== null && ($this->buildings[$wallIdx]['alive'] ?? false)) {
                    $this->units[$i]['target'] = $wallIdx;
                    $this->attackBuilding($i, $wallIdx, 1.0);

                    return;
                }
            }

            $d = $this->dist($u['x'], $u['y'], $wx, $wy);
            if ($d <= $remaining) {
                $this->units[$i]['x'] = $wx;
                $this->units[$i]['y'] = $wy;
                $this->units[$i]['path_idx']++;
                $remaining -= $d;
            } else {
                $this->units[$i]['x'] += (($wx - $u['x']) / max(0.001, $d)) * $remaining;
                $this->units[$i]['y'] += (($wy - $u['y']) / max(0.001, $d)) * $remaining;
                $remaining = 0;
            }
            $u = $this->units[$i];
        }
    }

    private function attackBuilding(int $i, int $bi, float $dmgMul): void
    {
        $u = $this->units[$i];
        if ($u['cooldown'] > 0) {
            return;
        }

        $b = $this->buildings[$bi];
        $mult = $this->preferenceMultiplier($u, $b);
        $damage = $u['dps'] * $u['attack_speed'] * $mult * $dmgMul;

        $this->units[$i]['cooldown'] = $u['attack_speed'];

        // Olay yalnızca hedef değiştiğinde yayılır; istemci vuruş animasyonunu
        // `attack_speed` ile kendisi döngüye alır. Tekrar dosyasını küçük tutar.
        if ($u['last_atk'] !== $b['id']) {
            $this->units[$i]['last_atk'] = $b['id'];
            $this->units[$i]['last_atk_unit'] = null;
            $this->events[] = ['t' => $this->tick, 'e' => 'atk', 'u' => $u['id'], 'b' => $b['id']];
        }

        $this->damageBuilding($bi, $damage);

        if ($u['splash'] > 0) {
            foreach ($this->buildings as $j => $other) {
                if ($j === $bi || ! $other['alive'] || $other['is_trap']) {
                    continue;
                }
                if ($this->distToBuilding($b['cx'], $b['cy'], $other) <= $u['splash']) {
                    $this->damageBuilding($j, $damage * 0.5);
                }
            }
        }
    }

    private function preferenceMultiplier(array $u, array $b): float
    {
        return match ($u['pref']) {
            'duvar'   => $b['category'] === 'duvar' ? $u['mult'] : 1.0,
            'savunma' => $b['category'] === 'savunma' ? $u['mult'] : 1.0,
            'kaynak'  => in_array($b['category'], ['kaynak', 'depo'], true) ? $u['mult'] : 1.0,
            'otag'    => $b['key'] === 'otag' ? $u['mult'] : 1.0,
            default   => 1.0,
        };
    }

    private function attackUnit(int $i, int $j, float $dmgMul): void
    {
        $u = $this->units[$i];
        if ($u['cooldown'] > 0) {
            return;
        }

        $this->units[$i]['cooldown'] = $u['attack_speed'];

        $victimId = $this->units[$j]['id'];
        if ($u['last_atk_unit'] !== $victimId) {
            $this->units[$i]['last_atk_unit'] = $victimId;
            $this->units[$i]['last_atk'] = null;
            $this->events[] = [
                't' => $this->tick, 'e' => 'atku', 'u' => $u['id'], 'v' => $victimId,
            ];
        }

        $this->damageUnit($j, $u['dps'] * $u['attack_speed'] * $dmgMul);
    }

    private function nearestEnemyUnit(int $i, string $side, float $maxDist): ?int
    {
        $u = $this->units[$i];
        $best = null;
        $bestD = $maxDist;

        foreach ($this->units as $j => $o) {
            if ($j === $i || ! $o['alive'] || ! $o['deployed'] || $o['side'] !== $side) {
                continue;
            }
            // Yere bağlı birimler havadaki birime vuramaz.
            if ($o['air'] && ! $u['air'] && ($u['range'] < 1.5)) {
                continue;
            }
            $d = $this->dist($u['x'], $u['y'], $o['x'], $o['y']);
            if ($d < $bestD) {
                $bestD = $d;
                $best = $j;
            }
        }

        return $best;
    }

    private function stepDefenses(): void
    {
        foreach ($this->buildings as $i => $b) {
            if (! $b['alive'] || $b['dps'] <= 0 || $b['is_trap']) {
                continue;
            }
            if ($b['frozen_until'] > $this->tick) {
                continue;
            }

            $this->buildings[$i]['cooldown'] = max(0, $b['cooldown'] - $this->dt);
            if ($this->buildings[$i]['cooldown'] > 0) {
                continue;
            }

            $target = null;
            $bestD = INF;
            foreach ($this->units as $j => $u) {
                if (! $u['alive'] || ! $u['deployed'] || $u['side'] !== 'saldiran') {
                    continue;
                }
                if ($u['air'] && ! $b['targets_air']) {
                    continue;
                }
                if (! $u['air'] && ! $b['targets_ground']) {
                    continue;
                }
                $d = $this->distToBuilding($u['x'], $u['y'], $b);
                if ($d > $b['range'] || $d < $b['min_range']) {
                    continue;
                }
                if ($d < $bestD) {
                    $bestD = $d;
                    $target = $j;
                }
            }

            if ($target === null) {
                $this->buildings[$i]['target'] = null;

                continue;
            }

            $this->buildings[$i]['cooldown'] = $b['attack_speed'];
            $damage = $b['dps'] * $b['attack_speed'];

            $targetId = $this->units[$target]['id'];
            if (($b['target'] ?? null) !== $targetId) {
                $this->buildings[$i]['target'] = $targetId;
                $this->events[] = [
                    't' => $this->tick, 'e' => 'def', 'b' => $b['id'], 'u' => $targetId,
                ];
            }

            if ($b['splash'] > 0) {
                $tx = $this->units[$target]['x'];
                $ty = $this->units[$target]['y'];
                foreach ($this->units as $j => $u) {
                    // `deployed` denetimi şart: henüz inmemiş askerler iniş
                    // noktasında üst üste durur, alan hasarı onlara işlemez.
                    if (! $u['alive'] || ! $u['deployed'] || $u['side'] !== 'saldiran') {
                        continue;
                    }
                    if ($u['air'] && ! $b['targets_air']) {
                        continue;
                    }
                    if ($this->dist($tx, $ty, $u['x'], $u['y']) <= $b['splash']) {
                        $this->damageUnit($j, $damage);
                    }
                }
            } else {
                $this->damageUnit($target, $damage);
            }
        }
    }

    private function checkTraps(): void
    {
        if ($this->tick % 2 !== 0 || ! $this->hasUnfiredTrap) {
            return;
        }

        $remaining = 0;

        foreach ($this->buildings as $i => $b) {
            if (! $b['is_trap'] || ! $b['alive'] || $b['trap_fired']) {
                continue;
            }

            $remaining++;

            foreach ($this->units as $j => $u) {
                if (! $u['alive'] || ! $u['deployed'] || $u['side'] !== 'saldiran') {
                    continue;
                }
                if ($u['air'] !== $b['trap_air']) {
                    continue;
                }
                if ($this->distToBuilding($u['x'], $u['y'], $b) > $b['trap_radius']) {
                    continue;
                }

                $this->buildings[$i]['trap_fired'] = true;
                $this->buildings[$i]['alive'] = false;
                $this->events[] = ['t' => $this->tick, 'e' => 'trap', 'b' => $b['id']];

                // Kurt İni / Gizli Tuzak: asker doğuran tuzaklar
                if (! empty($b['spawn'])) {
                    $this->spawnDefenders($b['spawn'], $b['cx'], $b['cy']);
                    break;
                }

                foreach ($this->units as $k => $v) {
                    if (! $v['alive'] || ! $v['deployed']
                        || $v['side'] !== 'saldiran' || $v['air'] !== $b['trap_air']) {
                        continue;
                    }
                    if ($this->dist($b['cx'], $b['cy'], $v['x'], $v['y']) <= $b['trap_radius'] + 1.0) {
                        $this->damageUnit($k, $b['trap_damage']);
                    }
                }
                $remaining--;
                break;
            }
        }

        $this->hasUnfiredTrap = $remaining > 0;
    }

    private function checkClanCastle(): void
    {
        if ($this->clanCastleTriggered || ! $this->clanTroops) {
            return;
        }

        foreach ($this->buildings as $b) {
            if (! $b['is_clan_castle'] || ! $b['alive']) {
                continue;
            }

            foreach ($this->units as $u) {
                if (! $u['alive'] || ! $u['deployed'] || $u['side'] !== 'saldiran') {
                    continue;
                }
                if ($this->distToBuilding($u['x'], $u['y'], $b) <= max(6.0, $b['clan_radius'])) {
                    $this->clanCastleTriggered = true;
                    foreach ($this->clanTroops as $t) {
                        $this->spawnDefenders($t, $b['cx'], $b['cy']);
                    }

                    return;
                }
            }
        }
    }

    private function spawnDefenders(array $spec, float $x, float $y): void
    {
        $count = (int) ($spec['count'] ?? 1);
        for ($n = 0; $n < $count; $n++) {
            $unit = $this->makeUnit([
                'key'          => $spec['key'],
                'level'        => (int) ($spec['level'] ?? 1),
                'hp'           => (float) $spec['hp'],
                'dps'          => (float) $spec['dps'],
                'speed'        => (float) ($spec['speed'] ?? 2.0),
                'range'        => (float) ($spec['range'] ?? 0.6),
                'attack_speed' => (float) ($spec['attack_speed'] ?? 1.0),
                'air'          => (bool) ($spec['air'] ?? false),
                'x'            => $x + $this->rng->jitter(1.3),
                'y'            => $y + $this->rng->jitter(1.3),
                'deploy_tick'  => $this->tick,
            ], 'savunan');
            $unit['deployed'] = true;
            $this->units[] = $unit;
            $this->aliveDefenders++;

            $this->events[] = [
                't' => $this->tick, 'e' => 'spawn', 'u' => $unit['id'],
                'key' => $unit['key'], 'lv' => $unit['level'], 'hp' => (int) $unit['max_hp'],
                'air' => $unit['air'], 'side' => 'savunan',
                'x' => round($unit['x'], 2), 'y' => round($unit['y'], 2),
            ];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Hasar ve durum                                                      */
    /* ------------------------------------------------------------------ */

    private function damageBuilding(int $i, float $amount): void
    {
        if (! $this->buildings[$i]['alive']) {
            return;
        }

        $this->buildings[$i]['hp'] -= $amount;
        if ($this->buildings[$i]['hp'] > 0) {
            return;
        }

        $this->buildings[$i]['hp'] = 0;
        $this->buildings[$i]['alive'] = false;
        $b = $this->buildings[$i];
        $this->events[] = ['t' => $this->tick, 'e' => 'bdie', 'b' => $b['id']];

        // Yıkılan duvar/bina hücrelerini yolu açacak şekilde boşalt.
        for ($y = $b['gy']; $y < $b['gy'] + $b['h']; $y++) {
            for ($x = $b['gx']; $x < $b['gx'] + $b['w']; $x++) {
                $this->path->setCell($x, $y, 0);
                unset($this->wallMap[$y * $this->gridSize + $x]);
            }
        }

        // Kurt İni yıkılınca kurtlar çıkar.
        if (! empty($b['spawn']) && ! $b['is_trap']) {
            $this->spawnDefenders($b['spawn'], $b['cx'], $b['cy']);
        }

        // Yolu değişen askerler yeniden hesaplasın.
        foreach ($this->units as $j => $u) {
            if ($u['alive'] && $u['side'] === 'saldiran' && $u['target'] === $i) {
                $this->units[$j]['target'] = null;
                $this->units[$j]['path'] = [];
                $this->units[$j]['last_atk'] = null;
            }
        }
    }

    private function damageUnit(int $i, float $amount): void
    {
        if (! $this->units[$i]['alive']) {
            return;
        }

        $this->units[$i]['hp'] -= $amount;
        if ($this->units[$i]['hp'] > 0) {
            return;
        }

        $this->units[$i]['hp'] = 0;
        $this->units[$i]['alive'] = false;

        if ($this->units[$i]['side'] === 'savunan') {
            $this->aliveDefenders--;
        } else {
            $this->aliveAttackers--;
        }

        $this->events[] = ['t' => $this->tick, 'e' => 'udie', 'u' => $this->units[$i]['id']];
    }

    /** Can çubukları için saniyede bir toplu durum gönderimi. */
    private function syncHp(): void
    {
        $bHp = [];
        foreach ($this->buildings as $b) {
            $key = 'b'.$b['id'];
            $hp = (int) ceil($b['hp']);
            if (($this->lastHp[$key] ?? -1) !== $hp) {
                $this->lastHp[$key] = $hp;
                $bHp[$b['id']] = $hp;
            }
        }

        $uHp = [];
        foreach ($this->units as $u) {
            $key = 'u'.$u['id'];
            $hp = (int) ceil($u['hp']);
            if (($this->lastHp[$key] ?? -1) !== $hp) {
                $this->lastHp[$key] = $hp;
                $uHp[$u['id']] = $hp;
            }
        }

        if ($bHp || $uHp) {
            $event = ['t' => $this->tick, 'e' => 'hp'];
            if ($bHp) {
                $event['b'] = $bHp;
            }
            if ($uHp) {
                $event['u'] = $uHp;
            }
            $this->events[] = $event;
        }
    }

    private function countingBuildings(): int
    {
        return count(array_filter($this->buildings, fn ($b) => $b['counts']));
    }

    private function destroyedCount(): int
    {
        return count(array_filter($this->buildings, fn ($b) => $b['counts'] && ! $b['alive']));
    }

    private function isOtagDestroyed(): bool
    {
        foreach ($this->buildings as $b) {
            if ($b['key'] === 'otag') {
                return ! $b['alive'];
            }
        }

        return false;
    }

    private function anyAttackerLeft(): bool
    {
        if ($this->aliveAttackers > 0 || $this->pendingDeploys > 0) {
            return true;
        }

        foreach ($this->spells as $s) {
            if (empty($s['cast'])) {
                return true;
            }
        }

        return false;
    }

    private function dist(float $ax, float $ay, float $bx, float $by): float
    {
        return sqrt(($ax - $bx) ** 2 + ($ay - $by) ** 2);
    }

    /** Noktadan binanın dikdörtgen sınırına uzaklık. */
    private function distToBuilding(float $x, float $y, array $b): float
    {
        $dx = max(0.0, abs($x - $b['cx']) - $b['w'] / 2);
        $dy = max(0.0, abs($y - $b['cy']) - $b['h'] / 2);

        return sqrt($dx * $dx + $dy * $dy);
    }
}
