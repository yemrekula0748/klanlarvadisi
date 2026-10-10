<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GameException;
use App\Http\Controllers\Controller;
use App\Models\PlayerHero;
use App\Services\ProgressionService;
use App\Services\TrainingService;
use App\Services\VillageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArmyController extends Controller
{
    public function __construct(
        private readonly TrainingService $training,
        private readonly VillageService $villages,
    ) {}

    public function train(Request $request): JsonResponse
    {
        $data = $request->validate([
            'troop'    => ['required', 'string', 'max:48'],
            'quantity' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $village = $this->village($request);
        $this->training->train($village, $data['troop'], $data['quantity']);

        return response()->json($this->state($request), 201);
    }

    public function cancelTraining(Request $request, int $item): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $village = $this->village($request);
        $this->training->cancelTraining($village, $item, (int) ($data['quantity'] ?? 0));

        return response()->json($this->state($request));
    }

    public function brew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spell'    => ['required', 'string', 'max:48'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $village = $this->village($request);
        $this->training->brew($village, $data['spell'], $data['quantity']);

        return response()->json($this->state($request), 201);
    }

    public function upgradeTroop(Request $request): JsonResponse
    {
        $data = $request->validate([
            'troop' => ['required', 'string', 'max:48'],
        ]);

        $village = $this->village($request);
        $this->training->upgradeTroop($village, $data['troop']);

        return response()->json($this->state($request));
    }

    /** Kahramanı Kurgan'da uyandır. */
    public function unlockHero(Request $request, ProgressionService $progression): JsonResponse
    {
        $data = $request->validate([
            'hero' => ['required', 'string', 'max:48'],
        ]);

        $player = $request->attributes->get('player');
        $village = $this->village($request);

        $hero = $player->heroes()->with('type')->get()
            ->firstWhere(fn ($ph) => $ph->type->key === $data['hero']);

        if (! $hero) {
            throw GameException::bulunamadi('Kahraman bulunamadı.');
        }
        if ($hero->unlocked) {
            throw new GameException('Bu kahraman zaten uyandı.', 'zaten_uyandi');
        }

        $kurgan = $this->training->bestLevelOf($village, 'kurgan');
        if ($kurgan < 1) {
            throw new GameException('Önce Kurgan kurmalısın.', 'kurgan_yok');
        }
        if ($village->otag_level < $hero->type->unlock_otag_level) {
            throw new GameException(
                "{$hero->type->name} için Otağ {$hero->type->unlock_otag_level}. seviyede olmalı.",
                'otag_seviyesi'
            );
        }

        $cost = ['kut' => 3000 * $hero->type->sort];
        app(\App\Services\ResourceService::class)->sync($village);
        app(\App\Services\ResourceService::class)->spend($village, $cost);

        $hero->update(['unlocked' => true, 'level' => 1]);

        return response()->json($this->state($request));
    }

    public function upgradeHero(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hero' => ['required', 'string', 'max:48'],
        ]);

        $player = $request->attributes->get('player');
        $village = $this->village($request);

        $hero = $player->heroes()->with('type')->get()
            ->firstWhere(fn ($ph) => $ph->type->key === $data['hero']);

        if (! $hero || ! $hero->unlocked) {
            throw new GameException('Bu kahraman henüz uyanmadı.', 'uyanmadi');
        }
        if ($hero->is_upgrading) {
            throw new GameException('Kahraman hâlihazırda güçlendiriliyor.', 'gelistirme_suruyor');
        }

        $next = $hero->level + 1;
        if ($next > $hero->type->max_level) {
            throw new GameException('Kahraman azami seviyede.', 'azami_seviye');
        }

        $stats = $hero->type->statsAt($next);
        if (! $stats) {
            throw GameException::bulunamadi('Seviye tanımsız.');
        }

        app(\App\Services\ResourceService::class)->sync($village);
        app(\App\Services\ResourceService::class)->spend($village, ['kut' => (int) $stats->upgrade_cost_kut]);

        $hero->update([
            'is_upgrading'    => true,
            'upgrade_ends_at' => now()->addSeconds($stats->upgrade_seconds),
        ]);

        return response()->json($this->state($request));
    }

    private function village(Request $request)
    {
        $village = $request->attributes->get('player')->village;
        if (! $village) {
            throw GameException::bulunamadi('Obanız bulunamadı.');
        }

        return $village;
    }

    private function state(Request $request): array
    {
        return $this->villages->state($request->attributes->get('player'));
    }
}
