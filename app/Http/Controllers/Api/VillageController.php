<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GameException;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Village;
use App\Services\BuildService;
use App\Services\LayoutGenerator;
use App\Services\VillageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    public function __construct(
        private readonly VillageService $villages,
        private readonly BuildService $build,
    ) {}

    /** Unity'nin ana isteği: obanın tam durumu. */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->villages->state($request->attributes->get('player')));
    }

    /** Başka bir obayı görüntüle (saldırı öncesi keşif). */
    public function scout(Request $request, Village $village): JsonResponse
    {
        return response()->json($this->villages->scout($village));
    }

    public function place(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:48'],
            'x'   => ['required', 'integer', 'min:0', 'max:60'],
            'y'   => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        $village = $this->ownVillage($request);
        $building = $this->build->place($village, $data['key'], $data['x'], $data['y']);

        return response()->json([
            'building' => $this->buildingPayload($building),
            'village'  => $this->villages->state($request->attributes->get('player')),
        ], 201);
    }

    public function upgrade(Request $request, Building $building): JsonResponse
    {
        $village = $this->ownVillage($request);
        $this->assertOwns($village, $building);

        $this->build->upgrade($village, $building);

        return response()->json([
            'building' => $this->buildingPayload($building->refresh()),
            'village'  => $this->villages->state($request->attributes->get('player')),
        ]);
    }

    public function cancel(Request $request, Building $building): JsonResponse
    {
        $village = $this->ownVillage($request);
        $this->assertOwns($village, $building);

        $this->build->cancel($village, $building);

        return response()->json([
            'village' => $this->villages->state($request->attributes->get('player')),
        ]);
    }

    public function move(Request $request, Building $building): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0', 'max:60'],
            'y' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        $village = $this->ownVillage($request);
        $this->assertOwns($village, $building);

        $this->build->move($village, $building, $data['x'], $data['y']);

        return response()->json(['building' => $this->buildingPayload($building->refresh())]);
    }

    public function destroy(Request $request, Building $building): JsonResponse
    {
        $village = $this->ownVillage($request);
        $this->assertOwns($village, $building);

        $this->build->destroy($village, $building);

        return response()->json([
            'village' => $this->villages->state($request->attributes->get('player')),
        ]);
    }

    /** Gök Taşı ile inşaatı anında bitir. */
    public function finish(Request $request, Building $building): JsonResponse
    {
        $village = $this->ownVillage($request);
        $this->assertOwns($village, $building);

        $this->build->finishInstantly($village, $building);

        return response()->json([
            'village' => $this->villages->state($request->attributes->get('player')),
        ]);
    }

    /**
     * Birden fazla binayı tek istekte taşır — Unity'de "düzeni kaydet"
     * düğmesi bunu kullanır (her bina için ayrı istek atmaz).
     */
    public function saveLayout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'buildings'            => ['required', 'array', 'min:1', 'max:400'],
            'buildings.*.id'       => ['required', 'integer'],
            'buildings.*.x'        => ['required', 'integer', 'min:0', 'max:60'],
            'buildings.*.y'        => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        $village = $this->ownVillage($request);
        $owned = $village->buildings()->with('type')->get()->keyBy('id');

        // Hepsini birlikte doğrula: tek bir çakışma varsa hiçbiri uygulanmaz.
        $target = [];
        foreach ($data['buildings'] as $row) {
            $building = $owned->get($row['id']);
            if (! $building) {
                throw GameException::izinYok('Bu bina senin obanda değil.');
            }
            $target[$building->id] = ['b' => $building, 'x' => $row['x'], 'y' => $row['y']];
        }

        $size = config('game.grid.size');
        $margin = config('game.grid.margin');
        $occupied = [];

        foreach ($owned as $building) {
            $pos = $target[$building->id] ?? ['x' => $building->x, 'y' => $building->y];
            $w = $building->type->grid_w;
            $h = $building->type->grid_h;

            if ($pos['x'] < $margin || $pos['y'] < $margin
                || $pos['x'] + $w > $size - $margin
                || $pos['y'] + $h > $size - $margin) {
                throw new GameException("{$building->type->name} oba sınırının dışında.", 'izgara_disi');
            }

            for ($dy = 0; $dy < $h; $dy++) {
                for ($dx = 0; $dx < $w; $dx++) {
                    $cell = ($pos['y'] + $dy) * 100 + ($pos['x'] + $dx);
                    if (isset($occupied[$cell])) {
                        throw new GameException(
                            "{$building->type->name} başka bir binayla çakışıyor.",
                            'kare_dolu'
                        );
                    }
                    $occupied[$cell] = $building->id;
                }
            }
        }

        foreach ($target as $row) {
            $row['b']->update(['x' => $row['x'], 'y' => $row['y']]);
        }

        return response()->json([
            'message' => 'Düzen kaydedildi.',
            'village' => $this->villages->state($request->attributes->get('player')),
        ]);
    }

    /** Hazır düzen uygula (oyuncuya kolaylık). */
    public function autoLayout(Request $request, LayoutGenerator $layout): JsonResponse
    {
        $village = $this->ownVillage($request);
        $stats = $layout->build($village, $village->otag_level);

        return response()->json([
            'message' => 'Hazır düzen kuruldu.',
            'stats'   => $stats,
            'village' => $this->villages->state($request->attributes->get('player')),
        ]);
    }

    private function ownVillage(Request $request): Village
    {
        $village = $request->attributes->get('player')->village;
        if (! $village) {
            throw GameException::bulunamadi('Obanız bulunamadı.');
        }

        return $village;
    }

    private function assertOwns(Village $village, Building $building): void
    {
        if ($building->village_id !== $village->id) {
            throw GameException::izinYok('Bu bina senin obanda değil.');
        }
    }

    private function buildingPayload(Building $building): array
    {
        $building->loadMissing('type');

        return [
            'id'           => $building->id,
            'key'          => $building->type->key,
            'category'     => $building->type->category,
            'level'        => $building->level,
            'x'            => $building->x,
            'y'            => $building->y,
            'w'            => $building->type->grid_w,
            'h'            => $building->type->grid_h,
            'constructing' => $building->is_constructing,
            'target_level' => $building->target_level,
            'ends_at'      => $building->construction_ends_at?->toIso8601String(),
            'remaining'    => $building->remainingSeconds(),
        ];
    }
}
