<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Battle;
use App\Models\Building;
use App\Models\ClanWar;
use App\Models\ClanWarAttack;
use App\Models\ClanWarMember;
use App\Models\GameEvent;
use App\Models\Player;
use App\Models\PlayerHero;
use App\Models\PlayerSpell;
use App\Models\PlayerTroop;
use App\Models\Village;
use App\Services\Combat\BattleSimulator;
use Illuminate\Support\Facades\DB;

class BattleService
{
    public function __construct(
        private readonly ResourceService $resources,
        private readonly BuildService $build,
        private readonly TrainingService $training,
        private readonly TrophyService $trophies,
        private readonly ProgressionService $progression,
    ) {}

    /**
     * Saldırıyı çözümler.
     *
     * @param  array  $deployments  [['troop'=>'ceri','x'=>4,'y'=>20,'t'=>0], ...]
     * @param  array  $spellCasts   [['spell'=>'yildirim','x'=>20,'y'=>20,'t'=>40], ...]
     * @param  array  $heroCasts    ['asena', 'oguz_kagan']
     */
    public function attack(
        Player $attacker,
        Village $target,
        array $deployments,
        array $spellCasts = [],
        array $heroes = [],
        string $type = 'coklu',
        ?ClanWar $war = null,
    ): Battle {
        $attackerVillage = $attacker->village;
        if (! $attackerVillage) {
            throw GameException::bulunamadi('Saldıranın obası yok.');
        }
        if ($target->player_id === $attacker->id) {
            throw GameException::izinYok('Kendi obana saldıramazsın.');
        }

        $defender = $target->player;

        if ($type === 'coklu' && $defender->isProtected()) {
            throw new GameException('Bu oba kalkan altında.', 'kalkan_var');
        }

        // Savunanın birikmiş kaynaklarını ve inşaatlarını güncelle (yağma için).
        $this->resources->sync($target);
        $this->build->settle($target);
        $this->training->settle($attackerVillage);

        $army = $this->buildArmy($attacker, $deployments, $spellCasts, $heroes);
        if (! $army['units']) {
            throw new GameException('Hiç asker göndermedin.', 'ordu_bos');
        }

        // Gönderilen ordu, ordugâh kapasitesini aşamaz (istemci doğrulaması
        // atlanırsa sunucu burada durdurur).
        $capacity = $this->training->armyCapacity($attackerVillage);
        $housing = $this->armyHousing($army['used']);
        if ($capacity > 0 && $housing > $capacity) {
            throw new GameException(
                "Ordun ordugâh kapasitesini aşıyor ({$housing}/{$capacity}).",
                'ordu_kapasitesi'
            );
        }

        $defense = $this->buildDefense($target, $war);
        $seed = random_int(1, 2_000_000_000);

        $result = (new BattleSimulator)->run($defense, $army, $seed);

        return DB::transaction(function () use (
            $attacker, $attackerVillage, $defender, $target,
            $army, $defense, $result, $seed, $type, $war
        ) {
            // 1) Kullanılan askerler ve tılsımlar tükenir.
            $this->consumeArmy($attacker, $army);

            // 2) Yağma: yıkım oranıyla ölçeklenir.
            $loot = [];
            if ($type !== 'savas') {
                $available = $this->resources->lootable($target, $attackerVillage->otag_level);
                $factor = min(1.0, $result['destruction'] / 100);
                $loot = array_map(fn ($v) => (int) floor($v * $factor), $available);

                foreach ($loot as $res => $amount) {
                    if ($amount > 0) {
                        $target->{$res} = max(0, $target->{$res} - $amount);
                    }
                }
                $target->save();
                $loot = $this->resources->add($attackerVillage, $loot);
            }
            $loot = array_merge(['gold' => 0, 'grain' => 0, 'iron' => 0, 'kut' => 0], $loot);

            // 3) Kupa değişimi (savaşlarda kupa yoktur).
            [$deltaA, $deltaD] = $type === 'savas'
                ? [0, 0]
                : $this->trophies->resolve($attacker, $defender, $result['stars'], $result['destruction']);

            // 4) Savunan oyuncuya kalkan.
            if ($type !== 'savas' && $result['stars'] > 0) {
                $hours = config("game.battle.shield_hours.{$result['stars']}", 12);
                $defender->shield_until = now()->addHours($hours);
            }
            $defender->defense_count++;
            if ($result['stars'] === 0) {
                $defender->defense_wins++;
            }
            $defender->save();

            $attacker->attack_count++;
            if ($result['stars'] > 0) {
                $attacker->attack_wins++;
            }
            $attacker->total_loot += array_sum($loot);
            $attacker->guard_until = now()->addHours(config('game.battle.guard_hours'));
            $attacker->save();

            $target->last_attacked_at = now();
            $target->save();

            // 5) Kahramanlar yaralanır (yenilenme süresi).
            $this->applyHeroRegen($attacker, $army);

            // 6) Tecrübe
            $this->progression->addXp($attacker, 2 + $result['stars'] * 8);

            $battle = Battle::create([
                'attacker_player_id'  => $attacker->id,
                'defender_player_id'  => $defender->id,
                'defender_village_id' => $target->id,
                'clan_war_id'         => $war?->id,
                'type'                => $type,
                'stars'               => $result['stars'],
                'destruction_percent' => $result['destruction'],
                'duration_seconds'    => $result['duration'],
                'loot_gold'           => $loot['gold'],
                'loot_grain'          => $loot['grain'],
                'loot_iron'           => $loot['iron'],
                'loot_kut'            => $loot['kut'],
                'trophies_attacker'   => $deltaA,
                'trophies_defender'   => $deltaD,
                'seed'                => $seed,
                'army'                => [
                    'units'  => $army['summary'],
                    'spells' => $army['spell_summary'],
                    'heroes' => $army['hero_summary'],
                ],
                'layout'              => $defense['buildings'],
                'replay'              => $result['replay'],
            ]);

            GameEvent::create([
                'player_id' => $defender->id,
                'type'      => 'saldiri_alindi',
                'title'     => "{$attacker->name} obana saldırdı",
                'body'      => "{$result['stars']} yıldız, %{$result['destruction']} yıkım.",
                'payload'   => ['battle_id' => $battle->id, 'stars' => $result['stars']],
            ]);

            if ($war) {
                $this->recordWarAttack($war, $battle, $attacker, $defender, $result);
                broadcast(new \App\Events\WarStateChanged($war->refresh(), 'saldiri'));
            } else {
                // Savunan oyuncu çevrimiçiyse anında haber alsın.
                broadcast(new \App\Events\VillageAttacked($battle));
            }

            return $battle;
        });
    }

    /** Gönderilen askerlerin kapladığı toplam alan. */
    private function armyHousing(array $used): int
    {
        if (! $used) {
            return 0;
        }

        $housing = \App\Models\TroopType::whereIn('key', array_keys($used))
            ->pluck('housing', 'key');

        $total = 0;
        foreach ($used as $key => $count) {
            $total += $count * (int) ($housing[$key] ?? 1);
        }

        return $total;
    }

    /** Oyuncunun ordusunu simülatör girdisine çevirir. */
    private function buildArmy(Player $player, array $deployments, array $spellCasts, array $heroKeys): array
    {
        $tickRate = (int) config('game.battle.tick_rate');
        $owned = $player->troops()->with('type.levels')->get()->keyBy(fn ($pt) => $pt->type->key);
        $spells = $player->spells()->with('type.levels')->get()->keyBy(fn ($ps) => $ps->type->key);
        $heroes = $player->heroes()->with('type.levels')->get()->keyBy(fn ($ph) => $ph->type->key);

        $units = [];
        $summary = [];
        $usedCounts = [];

        foreach ($deployments as $d) {
            $key = $d['troop'] ?? null;
            $pt = $key ? $owned->get($key) : null;
            if (! $pt) {
                continue;
            }

            $usedCounts[$key] = ($usedCounts[$key] ?? 0) + 1;
            if ($usedCounts[$key] > $pt->ready) {
                throw new GameException("Elinde yeterli {$pt->type->name} yok.", 'asker_yetersiz');
            }

            $stats = $pt->type->statsAt($pt->level);
            if (! $stats) {
                continue;
            }

            $units[] = [
                'key'          => $key,
                'level'        => $pt->level,
                'hp'           => $stats->hitpoints,
                'dps'          => $stats->damage_per_second,
                'speed'        => $pt->type->move_speed / 10,
                'range'        => $pt->type->attack_range,
                'attack_speed' => $pt->type->attack_speed,
                'pref'         => $pt->type->preferred_target,
                'mult'         => $pt->type->target_damage_multiplier,
                'air'          => $pt->type->is_air,
                'splash'       => $pt->type->splash_radius,
                'x'            => (float) ($d['x'] ?? 0),
                'y'            => (float) ($d['y'] ?? 0),
                'deploy_tick'  => (int) round(($d['t'] ?? 0) * $tickRate),
            ];

            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }

        // Kahramanlar
        $heroSummary = [];
        foreach ($heroKeys as $hk) {
            $ph = $heroes->get($hk);
            if (! $ph || ! $ph->isAvailable()) {
                continue;
            }
            $stats = $ph->type->statsAt($ph->level);
            if (! $stats) {
                continue;
            }

            $spawn = $deployments[0] ?? ['x' => 2, 'y' => 22, 't' => 0];
            $units[] = [
                'key'          => $hk,
                'level'        => $ph->level,
                'hp'           => $stats->hitpoints,
                'dps'          => $stats->damage_per_second,
                'speed'        => $ph->type->move_speed / 10,
                'range'        => $ph->type->attack_range,
                'attack_speed' => $ph->type->attack_speed,
                'pref'         => $ph->type->preferred_target,
                'mult'         => 1.0,
                'air'          => $ph->type->is_air,
                'splash'       => 0,
                'x'            => (float) $spawn['x'],
                'y'            => (float) $spawn['y'],
                'deploy_tick'  => 0,
                'is_hero'      => true,
                'ability'      => $stats->ability,
            ];
            $heroSummary[] = ['key' => $hk, 'level' => $ph->level];
        }

        // Tılsımlar
        $spellList = [];
        $spellSummary = [];
        $spellUsed = [];
        foreach ($spellCasts as $c) {
            $key = $c['spell'] ?? null;
            $ps = $key ? $spells->get($key) : null;
            if (! $ps) {
                continue;
            }

            $spellUsed[$key] = ($spellUsed[$key] ?? 0) + 1;
            if ($spellUsed[$key] > $ps->ready) {
                throw new GameException("Elinde yeterli {$ps->type->name} yok.", 'tilsim_yetersiz');
            }

            $stats = $ps->type->statsAt($ps->level);
            $spellList[] = [
                'key'    => $key,
                'level'  => $ps->level,
                'x'      => (float) ($c['x'] ?? 0),
                'y'      => (float) ($c['y'] ?? 0),
                'tick'   => (int) round(($c['t'] ?? 0) * $tickRate),
                'radius' => $ps->type->radius,
                'effect' => $stats?->effect ?? [],
            ];
            $spellSummary[$key] = ($spellSummary[$key] ?? 0) + 1;
        }

        return [
            'units'         => $units,
            'spells'        => $spellList,
            'summary'       => $summary,
            'spell_summary' => $spellSummary,
            'hero_summary'  => $heroSummary,
            'used'          => $usedCounts,
            'spells_used'   => $spellUsed,
        ];
    }

    /** Savunma düzenini simülatör girdisine çevirir. */
    public function buildDefense(Village $village, ?ClanWar $war = null): array
    {
        $buildings = [];

        foreach ($village->buildings()->with('type')->get() as $b) {
            if ($b->is_constructing && $b->level < 1) {
                continue; // henüz dikilmemiş
            }

            $type = $b->type;
            $stats = $b->stats();
            if (! $type || ! $stats) {
                continue;
            }

            $extra = $stats->extra ?? [];

            $buildings[] = [
                'id'             => $b->id,
                'key'            => $type->key,
                'category'       => $type->category,
                'level'          => $b->level,
                'x'              => $b->x,
                'y'              => $b->y,
                'w'              => $type->grid_w,
                'h'              => $type->grid_h,
                'hp'             => $stats->hitpoints,
                'dps'            => $stats->damage_per_second,
                'range'          => $stats->attack_range,
                'attack_speed'   => (float) ($extra['attack_speed'] ?? 1.0),
                'min_range'      => (float) ($extra['min_range'] ?? 0),
                'splash'         => (float) ($extra['splash'] ?? 0),
                'targets_air'    => (bool) ($extra['targets_air'] ?? false),
                'targets_ground' => (bool) ($extra['targets_ground'] ?? true),
                'is_trap'        => $type->category === 'tuzak',
                'trap_radius'    => (float) ($extra['trap_radius'] ?? 1.5),
                'trap_damage'    => (float) ($extra['trap_damage'] ?? 0),
                'trap_air'       => (bool) ($extra['trap_air'] ?? false),
                'spawn'          => $extra['spawn'] ?? null,
                'clan_radius'    => (float) ($extra['clan_radius'] ?? 0),
            ];
        }

        // Boy Otağı'ndaki bağış askerleri savunmaya katılır.
        $clanTroops = [];
        foreach ($village->clanTroops()->with('type.levels')->get() as $stock) {
            if ($stock->quantity <= 0) {
                continue;
            }
            $stats = $stock->type->statsAt($stock->level);
            if (! $stats) {
                continue;
            }

            $clanTroops[] = [
                'key'          => $stock->type->key,
                'level'        => $stock->level,
                'count'        => $stock->quantity,
                'hp'           => $stats->hitpoints,
                'dps'          => $stats->damage_per_second,
                'speed'        => $stock->type->move_speed / 10,
                'range'        => $stock->type->attack_range,
                'attack_speed' => $stock->type->attack_speed,
                'air'          => $stock->type->is_air,
            ];
        }

        return ['buildings' => $buildings, 'clan_troops' => $clanTroops];
    }

    private function consumeArmy(Player $player, array $army): void
    {
        foreach ($army['used'] as $key => $count) {
            PlayerTroop::where('player_id', $player->id)
                ->whereHas('type', fn ($q) => $q->where('key', $key))
                ->get()
                ->each(fn (PlayerTroop $pt) => $pt->decrement('ready', min($count, $pt->ready)));
        }

        foreach ($army['spells_used'] as $key => $count) {
            PlayerSpell::where('player_id', $player->id)
                ->whereHas('type', fn ($q) => $q->where('key', $key))
                ->get()
                ->each(fn (PlayerSpell $ps) => $ps->decrement('ready', min($count, $ps->ready)));
        }
    }

    private function applyHeroRegen(Player $player, array $army): void
    {
        foreach ($army['hero_summary'] as $h) {
            PlayerHero::where('player_id', $player->id)
                ->whereHas('type', fn ($q) => $q->where('key', $h['key']))
                ->get()
                ->each(function (PlayerHero $ph) {
                    $ph->update(['regen_until' => now()->addMinutes($ph->type->regen_minutes)]);
                });
        }
    }

    private function recordWarAttack(
        ClanWar $war, Battle $battle, Player $attacker, Player $defender, array $result
    ): void {
        $defenderMember = ClanWarMember::where('clan_war_id', $war->id)
            ->where('player_id', $defender->id)->first();
        $attackerMember = ClanWarMember::where('clan_war_id', $war->id)
            ->where('player_id', $attacker->id)->first();

        if (! $defenderMember || ! $attackerMember) {
            return;
        }

        // Savaşta yalnızca "yeni" yıldızlar sayılır.
        $newStars = max(0, $result['stars'] - $defenderMember->best_stars_against);

        $defenderMember->update([
            'best_stars_against'       => max($defenderMember->best_stars_against, $result['stars']),
            'best_destruction_against' => max($defenderMember->best_destruction_against, $result['destruction']),
        ]);

        $attackerMember->increment('attacks_used');
        $attackerMember->increment('stars_earned', $newStars);

        ClanWarAttack::create([
            'clan_war_id'         => $war->id,
            'battle_id'           => $battle->id,
            'attacker_player_id'  => $attacker->id,
            'defender_player_id'  => $defender->id,
            'stars'               => $result['stars'],
            'new_stars'           => $newStars,
            'destruction_percent' => $result['destruction'],
        ]);

        if ($attackerMember->clan_id === $war->clan_a_id) {
            $war->increment('stars_a', $newStars);
            $war->increment('destruction_a', $result['destruction']);
        } else {
            $war->increment('stars_b', $newStars);
            $war->increment('destruction_b', $result['destruction']);
        }

        $attacker->increment('war_stars', $newStars);
    }
}
