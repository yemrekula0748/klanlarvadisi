<?php

namespace App\Services;

use App\Models\Building;
use App\Models\BuildingType;
use App\Models\HeroType;
use App\Models\Player;
use App\Models\PlayerHero;
use App\Models\PlayerSpell;
use App\Models\PlayerTroop;
use App\Models\SpellType;
use App\Models\TroopType;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\DB;

class VillageService
{
    /** Yeni oyuncunun başlangıç düzeni: Otağ + iki tarla + iki maden + kışla. */
    private const STARTING_LAYOUT = [
        ['otag',          20, 20],
        ['tarla',         14, 15],
        ['tarla',         14, 19],
        ['altin_madeni',  26, 15],
        ['altin_madeni',  26, 19],
        ['ambar',         14, 24],
        ['hazine',        26, 24],
        ['kisla',         20, 26],
        ['ordugah',       16, 29],
        ['okcu_kulesi',   22, 14],
    ];

    public function __construct(
        private readonly WorldService $world,
        private readonly ResourceService $resources,
        private readonly BuildService $build,
        private readonly TrainingService $training,
    ) {}

    /** Kullanıcıdan oyuncu + oba yaratır. */
    public function createForUser(User $user, string $playerName, ?string $villageName = null): Player
    {
        return DB::transaction(function () use ($user, $playerName, $villageName) {
            [$x, $y, $biome] = $this->world->findSpawn();

            $player = Player::create([
                'user_id'   => $user->id,
                'name'      => $playerName,
                'gok_tasi'  => config('game.gok_tasi.start'),
                'trophies'  => config('game.trophies.start'),
                'guard_until' => now()->addDays(3), // yeni oyuncu koruması
                'last_seen_at' => now(),
            ]);

            $village = Village::create([
                'player_id'            => $player->id,
                'name'                 => $villageName ?: "{$playerName} Obası",
                'world_x'              => $x,
                'world_y'              => $y,
                'biome'                => $biome,
                'otag_level'           => 1,
                'gold'                 => 2000,
                'grain'                => 2000,
                'iron'                 => 0,
                'kut'                  => 0,
                'resources_updated_at' => now(),
                'builders'             => 2,
            ]);

            $this->placeStartingBuildings($village);
            $this->unlockStartingUnits($player);
            $this->world->placeVillage($village->refresh());

            return $player->refresh();
        });
    }

    private function placeStartingBuildings(Village $village): void
    {
        $types = BuildingType::whereIn('key', array_column(self::STARTING_LAYOUT, 0))
            ->get()
            ->keyBy('key');

        foreach (self::STARTING_LAYOUT as [$key, $x, $y]) {
            $type = $types->get($key);
            if (! $type) {
                continue;
            }

            Building::create([
                'village_id'       => $village->id,
                'building_type_id' => $type->id,
                'level'            => 1,
                'x'                => $x,
                'y'                => $y,
                'is_constructing'  => false,
                'collected_at'     => now(),
            ]);
        }
    }

    private function unlockStartingUnits(Player $player): void
    {
        foreach (TroopType::whereIn('key', ['ceri', 'okcu_kiz'])->get() as $type) {
            PlayerTroop::firstOrCreate(
                ['player_id' => $player->id, 'troop_type_id' => $type->id],
                ['level' => 1, 'ready' => 0]
            );
        }

        foreach (HeroType::all() as $hero) {
            PlayerHero::firstOrCreate(
                ['player_id' => $player->id, 'hero_type_id' => $hero->id],
                ['level' => 1, 'unlocked' => false]
            );
        }
    }

    /** Oyuncunun obasını güncelleyip tam durumu döndürür (Unity ana isteği). */
    public function state(Player $player): array
    {
        $village = $player->village;
        if (! $village) {
            return [];
        }

        $this->build->settle($village);
        $this->training->settle($village);
        $this->resources->sync($village);
        $village->load('buildings.type');

        $rates = $this->resources->productionRates($village);
        $caps = $this->resources->storageCaps($village);
        $builders = $this->build->builders($village);
        $armyUsage = $this->training->armyUsage($village);

        return [
            'player' => [
                'id'            => $player->id,
                'name'          => $player->name,
                'avatar'        => $player->avatar,
                'level'         => $player->level,
                'xp'            => (int) $player->xp,
                'trophies'      => $player->trophies,
                'best_trophies' => $player->best_trophies,
                'league'        => $player->league(),
                'gok_tasi'      => (int) $player->gok_tasi,
                'shield_until'  => $player->shield_until?->toIso8601String(),
                'guard_until'   => $player->guard_until?->toIso8601String(),
                'clan'          => $player->clan ? [
                    'id'    => $player->clan->id,
                    'name'  => $player->clan->name,
                    'tag'   => $player->clan->tag,
                    'badge' => $player->clan->badge,
                    'level' => $player->clan->level,
                    'role'  => $player->clan_role,
                ] : null,
                'stats' => [
                    'attack_wins'  => $player->attack_wins,
                    'defense_wins' => $player->defense_wins,
                    'war_stars'    => $player->war_stars,
                    'donated'      => $player->donated,
                    'received'     => $player->received,
                ],
            ],
            'village' => [
                'id'         => $village->id,
                'name'       => $village->name,
                'otag_level' => $village->otag_level,
                'biome'      => $village->biome,
                'world'      => ['x' => $village->world_x, 'y' => $village->world_y],
                'resources'  => $village->resources(),
                'production' => $rates,
                'capacity'   => $caps,
                'builders'   => $builders,
                'grid'       => config('game.grid.size'),
                'server_time' => now()->toIso8601String(),
            ],
            'buildings' => $village->buildings->map(fn (Building $b) => [
                'id'            => $b->id,
                'key'           => $b->type->key,
                'category'      => $b->type->category,
                'level'         => $b->level,
                'x'             => $b->x,
                'y'             => $b->y,
                'w'             => $b->type->grid_w,
                'h'             => $b->type->grid_h,
                'constructing'  => $b->is_constructing,
                'target_level'  => $b->target_level,
                'ends_at'       => $b->construction_ends_at?->toIso8601String(),
                'remaining'     => $b->remainingSeconds(),
            ])->all(),
            'army' => [
                'usage'  => $armyUsage,
                'troops' => $player->troops()->with('type')->get()->map(fn (PlayerTroop $pt) => [
                    'key'          => $pt->type->key,
                    'level'        => $pt->level,
                    'ready'        => $pt->ready,
                    'housing'      => $pt->type->housing,
                    'upgrading'    => $pt->is_upgrading,
                    'upgrade_ends' => $pt->upgrade_ends_at?->toIso8601String(),
                ])->all(),
                'spells' => $player->spells()->with('type')->get()->map(fn (PlayerSpell $ps) => [
                    'key'          => $ps->type->key,
                    'level'        => $ps->level,
                    'ready'        => $ps->ready,
                    'housing'      => $ps->type->housing,
                    'upgrading'    => $ps->is_upgrading,
                    'upgrade_ends' => $ps->upgrade_ends_at?->toIso8601String(),
                ])->all(),
                'heroes' => $player->heroes()->with('type')->get()->map(fn (PlayerHero $ph) => [
                    'key'          => $ph->type->key,
                    'name'         => $ph->type->name,
                    'level'        => $ph->level,
                    'unlocked'     => $ph->unlocked,
                    'available'    => $ph->isAvailable(),
                    'regen_until'  => $ph->regen_until?->toIso8601String(),
                    'upgrading'    => $ph->is_upgrading,
                    'upgrade_ends' => $ph->upgrade_ends_at?->toIso8601String(),
                ])->all(),
            ],
            'queues' => [
                'training' => $village->trainingQueue()->with('type')->get()->map(fn ($i) => [
                    'id'       => $i->id,
                    'key'      => $i->type->key,
                    'quantity' => $i->quantity,
                    'each'     => $i->seconds_each,
                    'starts_at'=> $i->starts_at->toIso8601String(),
                    'ends_at'  => $i->ends_at->toIso8601String(),
                ])->all(),
                'brewing' => $village->brewingQueue()->with('type')->get()->map(fn ($i) => [
                    'id'       => $i->id,
                    'key'      => $i->type->key,
                    'quantity' => $i->quantity,
                    'each'     => $i->seconds_each,
                    'ends_at'  => $i->ends_at->toIso8601String(),
                ])->all(),
            ],
            'clan_troops' => $village->clanTroops()->with('type')->get()->map(fn ($s) => [
                'key'      => $s->type->key,
                'level'    => $s->level,
                'quantity' => $s->quantity,
            ])->all(),
            'events' => $player->events()->whereNull('read_at')->latest()->limit(20)->get()->map(fn ($e) => [
                'id'      => $e->id,
                'type'    => $e->type,
                'title'   => $e->title,
                'body'    => $e->body,
                'payload' => $e->payload,
                'at'      => $e->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    /** Başka bir oyuncunun obasını (saldırı öncesi keşif) döndürür. */
    public function scout(Village $village): array
    {
        $this->resources->sync($village);
        $this->build->settle($village);
        $village->load('buildings.type', 'player.clan');

        return [
            'village' => [
                'id'         => $village->id,
                'name'       => $village->name,
                'otag_level' => $village->otag_level,
                'biome'      => $village->biome,
                'world'      => ['x' => $village->world_x, 'y' => $village->world_y],
            ],
            'player' => [
                'id'       => $village->player->id,
                'name'     => $village->player->name,
                'trophies' => $village->player->trophies,
                'league'   => $village->player->league(),
                'clan'     => $village->player->clan ? [
                    'name'  => $village->player->clan->name,
                    'tag'   => $village->player->clan->tag,
                    'badge' => $village->player->clan->badge,
                ] : null,
            ],
            'loot' => $this->resources->lootable($village, request()->user()?->player?->village?->otag_level ?? 1),
            'buildings' => $village->buildings->map(fn (Building $b) => [
                'id'       => $b->id,
                'key'      => $b->type->key,
                'category' => $b->type->category,
                'level'    => $b->level,
                'x'        => $b->x,
                'y'        => $b->y,
                'w'        => $b->type->grid_w,
                'h'        => $b->type->grid_h,
            ])->all(),
        ];
    }
}
