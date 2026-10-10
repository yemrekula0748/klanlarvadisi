<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Clan;
use App\Models\ClanWar;
use App\Models\ClanWarMember;
use App\Models\GameEvent;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class ClanWarService
{
    public function __construct(private readonly BattleService $battles) {}

    /** İki boy arasında savaş başlatır (hazırlık günü). */
    public function declare(Clan $a, Clan $b, int $size): ClanWar
    {
        if ($a->id === $b->id) {
            throw new GameException('Bir boy kendisiyle savaşamaz.', 'ayni_boy');
        }
        if (! in_array($size, config('game.clan.war_sizes'), true)) {
            throw new GameException('Geçersiz savaş boyutu.', 'gecersiz_boyut');
        }
        if ($a->currentWar() || $b->currentWar()) {
            throw new GameException('Boylardan biri hâlihazırda savaşta.', 'savas_var');
        }

        $rosterA = $a->members()->orderByDesc('trophies')->limit($size)->get();
        $rosterB = $b->members()->orderByDesc('trophies')->limit($size)->get();

        if ($rosterA->count() < $size || $rosterB->count() < $size) {
            throw new GameException("Her iki boyda da en az {$size} üye olmalı.", 'uye_yetersiz');
        }

        return DB::transaction(function () use ($a, $b, $size, $rosterA, $rosterB) {
            $war = ClanWar::create([
                'clan_a_id'      => $a->id,
                'clan_b_id'      => $b->id,
                'size'           => $size,
                'state'          => 'hazirlik',
                'prep_ends_at'   => now()->addHours(config('game.clan.war_preparation_hours')),
                'battle_ends_at' => now()
                    ->addHours(config('game.clan.war_preparation_hours'))
                    ->addHours(config('game.clan.war_battle_hours')),
            ]);

            foreach ([[$a, $rosterA], [$b, $rosterB]] as [$clan, $roster]) {
                foreach ($roster as $i => $member) {
                    ClanWarMember::create([
                        'clan_war_id'  => $war->id,
                        'clan_id'      => $clan->id,
                        'player_id'    => $member->id,
                        'map_position' => $i + 1,
                        'layout'       => $this->snapshotLayout($member),
                    ]);

                    GameEvent::create([
                        'player_id' => $member->id,
                        'type'      => 'savas_basladi',
                        'title'     => 'Boy savaşı ilan edildi',
                        'body'      => "Rakip boy: ".($clan->id === $a->id ? $b->name : $a->name),
                        'payload'   => ['war_id' => $war->id],
                    ]);
                }
            }

            return $war;
        });
    }

    private function snapshotLayout(Player $player): array
    {
        $village = $player->village;
        if (! $village) {
            return [];
        }

        return $this->battles->buildDefense($village)['buildings'];
    }

    /** Hazırlık günü bitenleri savaşa, savaşı bitenleri sonuca taşır. */
    public function tick(): array
    {
        $started = 0;
        $ended = 0;

        ClanWar::where('state', 'hazirlik')
            ->where('prep_ends_at', '<=', now())
            ->get()
            ->each(function (ClanWar $war) use (&$started) {
                $war->update(['state' => 'savas']);
                broadcast(new \App\Events\WarStateChanged($war, 'savas_gunu'));
                $started++;
            });

        ClanWar::where('state', 'savas')
            ->where('battle_ends_at', '<=', now())
            ->get()
            ->each(function (ClanWar $war) use (&$ended) {
                $this->finish($war);
                broadcast(new \App\Events\WarStateChanged($war->refresh(), 'bitti'));
                $ended++;
            });

        return ['basladi' => $started, 'bitti' => $ended];
    }

    public function finish(ClanWar $war): ClanWar
    {
        return DB::transaction(function () use ($war) {
            $winner = null;

            if ($war->stars_a > $war->stars_b) {
                $winner = $war->clan_a_id;
            } elseif ($war->stars_b > $war->stars_a) {
                $winner = $war->clan_b_id;
            } elseif ($war->destruction_a > $war->destruction_b) {
                $winner = $war->clan_a_id;
            } elseif ($war->destruction_b > $war->destruction_a) {
                $winner = $war->clan_b_id;
            }

            $war->update(['state' => 'bitti', 'winner_clan_id' => $winner]);

            foreach ([$war->clanA, $war->clanB] as $clan) {
                if (! $clan) {
                    continue;
                }
                if ($clan->id === $winner) {
                    $clan->increment('war_wins');
                    $clan->increment('war_streak');
                    $clan->increment('xp', config('game.clan.xp_per_war_win'));
                } else {
                    $clan->increment('war_losses');
                    $clan->update(['war_streak' => 0]);
                }
                $clan->update(['level' => $clan->levelFromXp()]);
            }

            // Ganimet: savaşa katılan her üyeye yıldızına göre ödül.
            foreach ($war->members as $member) {
                $player = $member->player;
                $village = $player?->village;
                if (! $village) {
                    continue;
                }

                $won = $member->clan_id === $winner;
                $base = 2500 * $village->otag_level;
                $reward = (int) ($base * ($won ? 1.0 : 0.35) * (1 + $member->stars_earned * 0.3));

                app(ResourceService::class)->add($village, [
                    'gold'  => $reward,
                    'grain' => $reward,
                    'iron'  => (int) ($reward * 0.1),
                ]);

                GameEvent::create([
                    'player_id' => $player->id,
                    'type'      => 'savas_bitti',
                    'title'     => $won ? 'Boy savaşını kazandık!' : 'Boy savaşı kaybedildi',
                    'body'      => "{$member->stars_earned} yıldız kazandın. Ganimet alındı.",
                    'payload'   => ['war_id' => $war->id, 'reward' => $reward],
                ]);
            }

            return $war->refresh();
        });
    }

    /** Savaş haritasında oyuncunun saldırabileceği hedefler. */
    public function targetsFor(ClanWar $war, Player $player): array
    {
        $me = ClanWarMember::where('clan_war_id', $war->id)->where('player_id', $player->id)->first();
        if (! $me) {
            throw GameException::izinYok('Bu savaşta yer almıyorsun.');
        }

        $opponentClanId = $war->opponentOf($me->clan_id);

        return ClanWarMember::with('player:id,name,trophies')
            ->where('clan_war_id', $war->id)
            ->where('clan_id', $opponentClanId)
            ->orderBy('map_position')
            ->get()
            ->map(fn (ClanWarMember $m) => [
                'position'    => $m->map_position,
                'player'      => ['id' => $m->player_id, 'name' => $m->player?->name],
                'stars'       => $m->best_stars_against,
                'destruction' => $m->best_destruction_against,
            ])
            ->all();
    }

    /** Savaş saldırısı: hedefin savaş düzenine saldırılır. */
    public function attack(Player $player, ClanWar $war, Player $target, array $deployments, array $spells = [], array $heroes = []): \App\Models\Battle
    {
        if ($war->state !== 'savas') {
            throw new GameException('Savaş günü henüz başlamadı.', 'savas_gunu_degil');
        }

        $me = ClanWarMember::where('clan_war_id', $war->id)->where('player_id', $player->id)->first();
        $enemy = ClanWarMember::where('clan_war_id', $war->id)->where('player_id', $target->id)->first();

        if (! $me || ! $enemy) {
            throw GameException::izinYok('Bu savaşta yer almıyorsun.');
        }
        if ($me->clan_id === $enemy->clan_id) {
            throw GameException::izinYok('Kendi boyuna saldıramazsın.');
        }
        if ($me->attacks_used >= config('game.clan.war_attacks_per_member')) {
            throw new GameException('Savaş saldırı hakkın bitti.', 'saldiri_hakki_bitti');
        }

        return $this->battles->attack(
            $player,
            $target->village,
            $deployments,
            $spells,
            $heroes,
            'savas',
            $war
        );
    }
}
