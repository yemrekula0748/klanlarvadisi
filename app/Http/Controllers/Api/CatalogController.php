<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuildingType;
use App\Models\HeroType;
use App\Models\SpellType;
use App\Models\TroopType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Oyun kataloğu: bütün bina, asker, kahraman ve tılsım tanımları.
 * Unity açılışta bir kez çeker ve önbelleğe alır (ETag ile).
 */
class CatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $payload = Cache::remember('katalog:'.config('game.version'), 3600, fn () => [
            'version'   => config('game.version'),
            'grid'      => config('game.grid'),
            'resources' => config('game.resource_labels'),
            'battle'    => [
                'tick_rate'    => config('game.battle.tick_rate'),
                'max_duration' => config('game.battle.max_duration'),
                'preparation'  => config('game.battle.preparation'),
            ],
            'leagues'   => config('game.trophies.league_tiers'),
            'buildings' => $this->buildings(),
            'troops'    => $this->troops(),
            'heroes'    => $this->heroes(),
            'spells'    => $this->spells(),
            'biomes'    => \App\Services\WorldService::BIOMES,
            'world'     => [
                'seed'   => config('game.world.seed'),
                'width'  => config('game.world.width'),
                'height' => config('game.world.height'),
            ],
        ]);

        return response()->json($payload)
            ->setEtag(md5(json_encode($payload)))
            ->header('Cache-Control', 'public, max-age=600');
    }

    private function buildings(): array
    {
        return BuildingType::with('levels')->orderBy('sort')->get()->map(fn (BuildingType $t) => [
            'key'         => $t->key,
            'name'        => $t->name,
            'category'    => $t->category,
            'produces'    => $t->produces,
            'stores'      => $t->stores,
            'description' => $t->description,
            'lore'        => $t->lore,
            'sprite'      => $t->sprite,
            'max_level'   => $t->max_level,
            'w'           => $t->grid_w,
            'h'           => $t->grid_h,
            'counts'      => $t->count_per_otag_level,
            'levels'      => $t->levels->map(fn ($l) => [
                'level'      => $l->level,
                'cost'       => $l->costs(),
                'seconds'    => $l->build_seconds,
                'hp'         => $l->hitpoints,
                'production' => $l->production_per_hour,
                'storage'    => $l->storage_capacity,
                'dps'        => $l->damage_per_second,
                'range'      => (float) $l->attack_range,
                'capacity'   => $l->troop_capacity,
                'otag'       => $l->requires_otag_level,
                'xp'         => $l->xp_reward,
                'extra'      => $l->extra,
            ])->all(),
        ])->all();
    }

    private function troops(): array
    {
        return TroopType::with('levels')->orderBy('sort')->get()->map(fn (TroopType $t) => [
            'key'           => $t->key,
            'name'          => $t->name,
            'category'      => $t->category,
            'description'   => $t->description,
            'lore'          => $t->lore,
            'sprite'        => $t->sprite,
            'housing'       => $t->housing,
            'train_seconds' => $t->train_seconds,
            'cost'          => $t->trainCosts(),
            'max_level'     => $t->max_level,
            'preferred'     => $t->preferred_target,
            'multiplier'    => (float) $t->target_damage_multiplier,
            'move_speed'    => (float) $t->move_speed,
            'range'         => (float) $t->attack_range,
            'attack_speed'  => (float) $t->attack_speed,
            'splash'        => (float) $t->splash_radius,
            'is_air'        => $t->is_air,
            'unlock_kisla'  => $t->unlock_kisla_level,
            'levels'        => $t->levels->map(fn ($l) => [
                'level'    => $l->level,
                'hp'       => $l->hitpoints,
                'dps'      => $l->damage_per_second,
                'cost'     => ['iron' => $l->upgrade_cost_iron, 'kut' => $l->upgrade_cost_kut],
                'seconds'  => $l->upgrade_seconds,
                'demirci'  => $l->requires_demirci_level,
                'extra'    => $l->extra,
            ])->all(),
        ])->all();
    }

    private function heroes(): array
    {
        return HeroType::with('levels')->orderBy('sort')->get()->map(fn (HeroType $t) => [
            'key'           => $t->key,
            'name'          => $t->name,
            'title'         => $t->title,
            'lore'          => $t->lore,
            'sprite'        => $t->sprite,
            'max_level'     => $t->max_level,
            'unlock_otag'   => $t->unlock_otag_level,
            'regen_minutes' => $t->regen_minutes,
            'move_speed'    => (float) $t->move_speed,
            'range'         => (float) $t->attack_range,
            'attack_speed'  => (float) $t->attack_speed,
            'levels'        => $t->levels->map(fn ($l) => [
                'level'   => $l->level,
                'hp'      => $l->hitpoints,
                'dps'     => $l->damage_per_second,
                'cost'    => ['kut' => $l->upgrade_cost_kut],
                'seconds' => $l->upgrade_seconds,
                'ability' => [
                    'name'        => $l->ability_name,
                    'description' => $l->ability_description,
                    'data'        => $l->ability,
                ],
            ])->all(),
        ])->all();
    }

    private function spells(): array
    {
        return SpellType::with('levels')->orderBy('sort')->get()->map(fn (SpellType $t) => [
            'key'          => $t->key,
            'name'         => $t->name,
            'category'     => $t->category,
            'description'  => $t->description,
            'lore'         => $t->lore,
            'sprite'       => $t->sprite,
            'housing'      => $t->housing,
            'brew_seconds' => $t->brew_seconds,
            'cost'         => ['kut' => $t->cost_kut, 'iron' => $t->cost_iron],
            'max_level'    => $t->max_level,
            'radius'       => (float) $t->radius,
            'unlock_buyu'  => $t->unlock_buyu_level,
            'levels'       => $t->levels->map(fn ($l) => [
                'level'   => $l->level,
                'effect'  => $l->effect,
                'cost'    => ['kut' => $l->upgrade_cost_kut],
                'seconds' => $l->upgrade_seconds,
            ])->all(),
        ])->all();
    }
}
