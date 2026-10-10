<?php

namespace Database\Seeders;

use App\Models\BuildingLevel;
use App\Models\BuildingType;
use App\Models\HeroLevel;
use App\Models\HeroType;
use App\Models\SpellLevel;
use App\Models\SpellType;
use App\Models\TroopLevel;
use App\Models\TroopType;
use App\Models\WorldRegion;
use Illuminate\Database\Seeder;

/**
 * Oyun kataloğunu (binalar, askerler, kahramanlar, tılsımlar, bölgeler)
 * `database/seeders/data/*.php` dosyalarındaki formüllerden üretir.
 *
 * Yeniden çalıştırılabilir: `php artisan db:seed --class=CatalogSeeder`
 * mevcut kayıtları günceller, oyuncu verisine dokunmaz.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBuildings();
        $this->seedTroops();
        $this->seedHeroes();
        $this->seedSpells();
        $this->seedRegions();
    }

    /* ------------------------------------------------------------------ */

    private function seedBuildings(): void
    {
        foreach (require database_path('seeders/data/buildings.php') as $def) {
            $type = BuildingType::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name'                 => $def['name'],
                    'category'             => $def['category'],
                    'produces'             => $def['produces'] ?? null,
                    'stores'               => $def['stores'] ?? null,
                    'description'          => $def['description'],
                    'lore'                 => $def['lore'],
                    'sprite'               => 'buildings/'.$def['key'],
                    'max_level'            => $def['max_level'],
                    'grid_w'               => $def['grid'][0],
                    'grid_h'               => $def['grid'][1],
                    'count_per_otag_level' => $def['count'],
                    'sort'                 => $def['sort'] ?? 0,
                ]
            );

            for ($lv = 1; $lv <= $def['max_level']; $lv++) {
                BuildingLevel::updateOrCreate(
                    ['building_type_id' => $type->id, 'level' => $lv],
                    $this->buildingLevel($def, $lv)
                );
            }

            // Azami seviye düşürüldüyse eski satırlar kalmasın.
            BuildingLevel::where('building_type_id', $type->id)
                ->where('level', '>', $def['max_level'])->delete();
        }
    }

    private function buildingLevel(array $def, int $lv): array
    {
        $step = $lv - 1;
        $g = fn (string $k, float $default = 1.0) => (float) ($def[$k.'_growth'] ?? $default);
        $base = fn (string $k, float $default = 0) => (float) ($def[$k] ?? $default);

        // Maliyet: tablo varsa onu, yoksa büyüme formülünü kullan.
        $costs = ['gold' => 0, 'grain' => 0, 'iron' => 0, 'kut' => 0];
        if (isset($def['cost_table'][$lv])) {
            foreach ($def['cost_table'][$lv] as $res => $amount) {
                $costs[$res] = (int) $amount;
            }
        } elseif ($lv > 1 || ! isset($def['cost_table'])) {
            foreach (($def['cost'] ?? []) as $res => $amount) {
                $costs[$res] = (int) round($amount * pow($g('cost', 2.0), $step));
            }
        }

        $seconds = isset($def['time_table'][$lv])
            ? (int) $def['time_table'][$lv]
            : (int) round($base('time') * pow($g('time', 2.0), $step));

        $extra = [];
        foreach (($def['extra'] ?? []) as $k => $v) {
            // Dizi verilen alanlar seviyeye göre indekslenir.
            $extra[$k] = is_array($v) ? ($v[$step] ?? end($v)) : $v;
        }

        // Tuzak hasarı ve asker doğurma sayıları seviyeye göre ölçeklenir.
        if (isset($def['extra']['trap_damage_base'])) {
            $extra['trap_damage'] = (int) round(
                $def['extra']['trap_damage_base'] * pow($def['extra']['trap_damage_growth'] ?? 1.3, $step)
            );
            unset($extra['trap_damage_base'], $extra['trap_damage_growth']);
        }
        if (isset($def['extra']['spawn_counts'])) {
            $extra['spawn'] = [
                'key'   => $def['extra']['spawn_key'],
                'count' => $def['extra']['spawn_counts'][$step] ?? end($def['extra']['spawn_counts']),
                'level' => min(10, 1 + intdiv($step, 2)),
                // Doğan birimin istatistikleri askerin kendi seviyesinden gelir;
                // burada savunma için makul bir taban verilir.
                'hp'    => (int) round(160 * pow(1.22, $step)),
                'dps'   => (int) round(24 * pow(1.23, $step)),
                'speed' => 3.2,
                'range' => 0.5,
            ];
            unset($extra['spawn_counts'], $extra['spawn_key']);
        }

        // Küresel denge çarpanları (config/game.php -> tuning)
        $tuning = config('game.tuning');
        $isDefense = in_array($def['category'], ['savunma', 'tuzak'], true);
        $hpMultiplier = match (true) {
            $def['category'] === 'duvar' => (float) $tuning['wall_hp'],
            $isDefense                   => (float) $tuning['defense_hp'],
            default                      => 1.0,
        };
        $dpsMultiplier = $isDefense ? (float) $tuning['defense_dps'] : 1.0;

        return [
            'cost_gold'           => $costs['gold'],
            'cost_grain'          => $costs['grain'],
            'cost_iron'           => $costs['iron'],
            'cost_kut'            => $costs['kut'],
            'build_seconds'       => $seconds,
            'hitpoints'           => (int) round($base('hp', 100) * pow($g('hp', 1.18), $step) * $hpMultiplier),
            'production_per_hour' => (int) round($base('prod') * pow($g('prod', 1.4), $step)),
            'storage_capacity'    => (int) round($base('storage') * pow($g('storage', 1.6), $step)),
            'damage_per_second'   => (int) round($base('dps') * pow($g('dps', 1.2), $step) * $dpsMultiplier),
            'attack_range'        => round($base('range'), 2),
            'troop_capacity'      => isset($def['capacity_table'])
                ? (int) ($def['capacity_table'][$step] ?? end($def['capacity_table']))
                : (int) round($base('capacity') * pow($g('capacity', 1.25), $step)),
            'requires_otag_level' => $this->requiredOtag($def, $lv),
            'xp_reward'           => (int) round($base('xp', 1) * pow($g('xp', 1.4), $step)),
            'extra'               => $extra ?: null,
        ];
    }

    /** `otag` tablosundaki en son eşiği uygular (ara seviyeler devralır). */
    private function requiredOtag(array $def, int $lv): int
    {
        $map = $def['otag'] ?? [1 => 1];
        $required = 1;
        for ($i = 1; $i <= $lv; $i++) {
            if (isset($map[$i])) {
                $required = (int) $map[$i];
            }
        }

        return $required;
    }

    /* ------------------------------------------------------------------ */

    private function seedTroops(): void
    {
        foreach (require database_path('seeders/data/troops.php') as $def) {
            $costs = array_merge(['gold' => 0, 'grain' => 0, 'iron' => 0, 'kut' => 0], $def['cost']);

            $type = TroopType::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name'                     => $def['name'],
                    'category'                 => $def['category'],
                    'description'              => $def['description'],
                    'lore'                     => $def['lore'],
                    'sprite'                   => 'troops/'.$def['key'],
                    'housing'                  => $def['housing'],
                    'train_seconds'            => $def['train'],
                    'train_cost_gold'          => $costs['gold'],
                    'train_cost_grain'         => $costs['grain'],
                    'train_cost_iron'          => $costs['iron'],
                    'train_cost_kut'           => $costs['kut'],
                    'max_level'                => $def['max_level'],
                    'preferred_target'         => $def['pref'] ?? 'yok',
                    'target_damage_multiplier' => $def['mult'] ?? 1,
                    'damage_type'              => ($def['splash'] ?? 0) > 0 ? 'alan' : 'tek',
                    'move_speed'               => $def['speed'],
                    'attack_range'             => $def['range'],
                    'attack_speed'             => $def['attack_speed'],
                    'splash_radius'            => $def['splash'] ?? 0,
                    'is_air'                   => $def['is_air'] ?? false,
                    'targets_air'              => ($def['range'] ?? 0) >= 1.5,
                    'unlock_kisla_level'       => $def['unlock'],
                    'sort'                     => $def['sort'] ?? 0,
                ]
            );

            for ($lv = 1; $lv <= $def['max_level']; $lv++) {
                $step = $lv - 1;

                $extra = [];
                foreach (($def['extra'] ?? []) as $k => $v) {
                    $extra[$k] = $v;
                }
                if (isset($def['extra']['heal_per_second_base'])) {
                    $extra['heal_per_second'] = (int) round(
                        $def['extra']['heal_per_second_base'] * pow($def['extra']['heal_growth'] ?? 1.18, $step)
                    );
                }
                if (isset($def['extra']['death_damage_base'])) {
                    $extra['death_damage'] = (int) round($def['extra']['death_damage_base'] * pow(1.2, $step));
                }

                TroopLevel::updateOrCreate(
                    ['troop_type_id' => $type->id, 'level' => $lv],
                    [
                        'hitpoints'                => (int) round(
                            $def['hp'] * pow($def['hp_growth'], $step) * config('game.tuning.troop_hp')
                        ),
                        'damage_per_second'        => (int) round(
                            $def['dps'] * pow($def['dps_growth'], $step) * config('game.tuning.troop_dps')
                        ),
                        'upgrade_cost_iron'        => $lv === 1 ? 0 : (int) round(300 * pow(1.85, $step) * $def['housing']),
                        'upgrade_cost_kut'         => $lv === 1 || ($def['dark'] ?? false) === false
                            ? 0
                            : (int) round(40 * pow(1.7, $step) * max(1, intdiv($def['housing'], 2))),
                        'upgrade_seconds'          => $lv === 1 ? 0 : (int) round(1800 * pow(1.6, $step)),
                        'requires_demirci_level'   => min(12, $lv === 1 ? 1 : $lv),
                        'extra'                    => $extra ?: null,
                    ]
                );
            }

            TroopLevel::where('troop_type_id', $type->id)
                ->where('level', '>', $def['max_level'])->delete();
        }
    }

    /* ------------------------------------------------------------------ */

    private function seedHeroes(): void
    {
        foreach (require database_path('seeders/data/heroes.php') as $def) {
            $type = HeroType::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name'              => $def['name'],
                    'title'             => $def['title'],
                    'lore'              => $def['lore'],
                    'sprite'            => 'heroes/'.$def['key'],
                    'max_level'         => $def['max_level'],
                    'unlock_otag_level' => $def['unlock_otag'],
                    'regen_minutes'     => $def['regen_minutes'],
                    'is_air'            => $def['is_air'] ?? false,
                    'move_speed'        => $def['speed'],
                    'attack_range'      => $def['range'],
                    'attack_speed'      => $def['attack_speed'],
                    'preferred_target'  => $def['pref'] ?? 'yok',
                    'sort'              => $def['sort'] ?? 0,
                ]
            );

            $a = $def['ability'];

            for ($lv = 1; $lv <= $def['max_level']; $lv++) {
                $step = $lv - 1;

                $ability = [
                    'key'         => $a['key'],
                    'name'        => $a['name'],
                    'radius'      => $a['radius'] ?? 5.0,
                    'duration'    => $a['duration'] ?? 6,
                    'speed_bonus' => $a['speed_bonus'] ?? 0,
                    'damage_bonus'=> $a['damage_bonus'] ?? 0,
                    'heal'        => (int) round(($a['heal_base'] ?? 0) * pow($a['heal_growth'] ?? 1.05, $step)),
                ];
                if (isset($a['heal_per_second'])) {
                    $ability['heal_per_second'] = (int) round($a['heal_per_second'] * pow(1.05, $step));
                }
                if (isset($a['summon'])) {
                    $ability['summon'] = $a['summon'];
                    $ability['summon_count'] = $a['summon_count'];
                    $ability['summon_hp'] = (int) round($a['summon_hp_base'] * pow(1.05, $step));
                    $ability['summon_dps'] = (int) round($a['summon_dps_base'] * pow(1.05, $step));
                }

                HeroLevel::updateOrCreate(
                    ['hero_type_id' => $type->id, 'level' => $lv],
                    [
                        'hitpoints'           => (int) round(
                            $def['hp'] * pow($def['hp_growth'], $step) * config('game.tuning.hero_hp')
                        ),
                        'damage_per_second'   => (int) round(
                            $def['dps'] * pow($def['dps_growth'], $step) * config('game.tuning.hero_dps')
                        ),
                        'upgrade_cost_kut'    => $lv === 1 ? 0 : (int) round(600 * pow(1.14, $step)),
                        'upgrade_seconds'     => $lv === 1 ? 0 : (int) round(3600 * pow(1.16, $step)),
                        'ability_name'        => $a['name'],
                        'ability_description' => $a['description'],
                        'ability'             => $ability,
                    ]
                );
            }

            HeroLevel::where('hero_type_id', $type->id)
                ->where('level', '>', $def['max_level'])->delete();
        }
    }

    /* ------------------------------------------------------------------ */

    private function seedSpells(): void
    {
        foreach (require database_path('seeders/data/spells.php') as $def) {
            $type = SpellType::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name'              => $def['name'],
                    'category'          => $def['category'],
                    'description'       => $def['description'],
                    'lore'              => $def['lore'],
                    'sprite'            => 'spells/'.$def['key'],
                    'housing'           => $def['housing'],
                    'brew_seconds'      => $def['brew'],
                    'cost_kut'          => $def['cost']['kut'] ?? 0,
                    'cost_iron'         => $def['cost']['iron'] ?? 0,
                    'max_level'         => $def['max_level'],
                    'radius'            => $def['radius'],
                    'unlock_buyu_level' => $def['unlock'],
                    'sort'              => $def['sort'] ?? 0,
                ]
            );

            for ($lv = 1; $lv <= $def['max_level']; $lv++) {
                $step = $lv - 1;
                $e = $def['effect'];
                $effect = [];

                if (isset($e['damage_base'])) {
                    $effect['damage'] = (int) round($e['damage_base'] * pow($e['damage_growth'] ?? 1.2, $step));
                }
                if (isset($e['duration'])) {
                    $effect['duration'] = round($e['duration'] * pow($e['duration_growth'] ?? 1.0, $step), 1);
                }
                if (isset($e['heal_base'])) {
                    $effect['heal_per_second'] = (int) round($e['heal_base'] * pow($e['heal_growth'] ?? 1.18, $step));
                }
                if (isset($e['speed_bonus_base'])) {
                    $effect['speed_bonus'] = round($e['speed_bonus_base'] * pow($e['bonus_growth'] ?? 1.08, $step), 3);
                }
                if (isset($e['damage_bonus_base'])) {
                    $effect['damage_bonus'] = round($e['damage_bonus_base'] * pow($e['bonus_growth'] ?? 1.08, $step), 3);
                }
                if (! empty($e['freeze'])) {
                    $effect['freeze'] = true;
                }
                if (isset($e['summon'])) {
                    $effect['summon'] = $e['summon'];
                    $effect['summon_count'] = (int) ($e['summon_count_base'] + $step * ($e['summon_count_growth'] ?? 1));
                    $effect['summon_hp'] = (int) round($e['summon_hp_base'] * pow($e['summon_growth'] ?? 1.18, $step));
                    $effect['summon_dps'] = (int) round($e['summon_dps_base'] * pow($e['summon_growth'] ?? 1.18, $step));
                }

                SpellLevel::updateOrCreate(
                    ['spell_type_id' => $type->id, 'level' => $lv],
                    [
                        'effect'            => $effect,
                        'upgrade_cost_kut'  => $lv === 1 ? 0 : (int) round(1200 * pow(1.45, $step)),
                        'upgrade_seconds'   => $lv === 1 ? 0 : (int) round(14400 * pow(1.4, $step)),
                    ]
                );
            }

            SpellLevel::where('spell_type_id', $type->id)
                ->where('level', '>', $def['max_level'])->delete();
        }
    }

    /* ------------------------------------------------------------------ */

    private function seedRegions(): void
    {
        $w = config('game.world.width');
        $h = config('game.world.height');

        foreach (config('regions', []) as $def) {
            WorldRegion::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name'     => $def['name'],
                    'lore'     => $def['lore'],
                    'biome'    => $def['biome'],
                    'center_x' => (int) round($def['at'][0] * $w),
                    'center_y' => (int) round($def['at'][1] * $h),
                    'radius'   => (int) round($def['radius'] * min($w, $h)),
                    'color'    => $def['color'],
                    'bonus'    => $def['bonus'] ?? null,
                ]
            );
        }
    }
}
