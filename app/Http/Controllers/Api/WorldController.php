<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorldRegion;
use App\Services\WorldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dünya haritası uçları.
 *
 * Arazi gönderilmez — Unity istemcisi aynı tohumla aynı araziyi kendisi
 * üretir (`WorldTerrain.cs`). Buradan yalnızca obalar, kamplar, damarlar
 * ve harabeler gibi "nesneler" gelir. 1600x1600 harita bu sayede tek bir
 * tile verisi indirmeden gezilebilir.
 */
class WorldController extends Controller
{
    public function __construct(private readonly WorldService $world) {}

    /** İstemcinin arazi üretimi için gereken bilgiler. */
    public function info(): JsonResponse
    {
        return response()->json([
            'seed'    => config('game.world.seed'),
            'width'   => config('game.world.width'),
            'height'  => config('game.world.height'),
            'chunk'   => config('game.world.chunk'),
            'biomes'  => WorldService::BIOMES,
            'regions' => WorldRegion::all()->map(fn (WorldRegion $r) => [
                'key'      => $r->key,
                'name'     => $r->name,
                'lore'     => $r->lore,
                'biome'    => $r->biome,
                'center'   => ['x' => $r->center_x, 'y' => $r->center_y],
                'radius'   => $r->radius,
                'color'    => $r->color,
                'bonus'    => $r->bonus,
                'clan'     => $r->controllingClan ? [
                    'id' => $r->controllingClan->id,
                    'name' => $r->controllingClan->name,
                    'tag' => $r->controllingClan->tag,
                ] : null,
            ])->all(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /** Belirtilen dikdörtgendeki dolu kareler. */
    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'w' => ['nullable', 'integer', 'min:1', 'max:64'],
            'h' => ['nullable', 'integer', 'min:1', 'max:64'],
        ]);

        $w = $data['w'] ?? config('game.world.chunk');
        $h = $data['h'] ?? config('game.world.chunk');

        return response()->json([
            'x'     => $data['x'],
            'y'     => $data['y'],
            'w'     => $w,
            'h'     => $h,
            'tiles' => $this->world->chunk($data['x'], $data['y'], $w, $h)->values(),
        ]);
    }

    /** Tek bir karenin ayrıntısı (arazi + üzerindeki nesne). */
    public function tile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
        ]);

        $terrain = $this->world->terrainAt($data['x'], $data['y']);
        $tile = \App\Models\WorldTile::with(['village', 'player.clan', 'region'])
            ->where('x', $data['x'])->where('y', $data['y'])->first();

        return response()->json([
            'terrain' => $terrain,
            'region'  => $this->world->regionAt($data['x'], $data['y'])?->only(['key', 'name', 'lore', 'color', 'bonus']),
            'object'  => $tile ? $this->world->presentTile($tile) : null,
        ]);
    }
}
