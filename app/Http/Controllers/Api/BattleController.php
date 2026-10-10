<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GameException;
use App\Http\Controllers\Controller;
use App\Models\Battle;
use App\Models\Player;
use App\Models\Village;
use App\Services\BattleService;
use App\Services\MatchmakingService;
use App\Services\VillageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BattleController extends Controller
{
    public function __construct(
        private readonly BattleService $battles,
        private readonly MatchmakingService $matchmaking,
        private readonly VillageService $villages,
    ) {}

    /** Rakip ara: kupaya yakın, kalkansız bir oba döndürür. */
    public function search(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');
        $village = $this->matchmaking->findOpponent($player);

        return response()->json([
            'target' => $this->villages->scout($village),
        ]);
    }

    /**
     * Saldırıyı çözümler. Sunucu simülasyonu tek doğruluk kaynağıdır;
     * istemci yalnızca dönen `replay`i oynatır.
     */
    public function attack(Request $request, Village $village): JsonResponse
    {
        $data = $request->validate([
            'deployments'         => ['required', 'array', 'min:1', 'max:350'],
            'deployments.*.troop' => ['required', 'string', 'max:48'],
            'deployments.*.x'     => ['required', 'numeric', 'min:0', 'max:60'],
            'deployments.*.y'     => ['required', 'numeric', 'min:0', 'max:60'],
            'deployments.*.t'     => ['nullable', 'numeric', 'min:0', 'max:180'],

            'spells'              => ['nullable', 'array', 'max:20'],
            'spells.*.spell'      => ['required', 'string', 'max:48'],
            'spells.*.x'          => ['required', 'numeric', 'min:0', 'max:60'],
            'spells.*.y'          => ['required', 'numeric', 'min:0', 'max:60'],
            'spells.*.t'          => ['nullable', 'numeric', 'min:0', 'max:180'],

            'heroes'              => ['nullable', 'array', 'max:4'],
            'heroes.*'            => ['string', 'max:48'],

            'type'                => ['nullable', 'string', 'in:coklu,intikam,npc'],
        ]);

        $player = $request->attributes->get('player');

        $battle = $this->battles->attack(
            $player,
            $village,
            $data['deployments'],
            $data['spells'] ?? [],
            $data['heroes'] ?? [],
            $data['type'] ?? 'coklu'
        );

        return response()->json([
            'battle'  => $this->battlePayload($battle, withReplay: true),
            'village' => $this->villages->state($player->refresh()),
        ], 201);
    }

    /** Saldırı ve savunma geçmişi. */
    public function index(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');

        $attacks = Battle::with('defender:id,name,trophies')
            ->where('attacker_player_id', $player->id)
            ->latest()->limit(25)->get()
            ->map(fn ($b) => $this->battlePayload($b));

        $defenses = Battle::with('attacker:id,name,trophies')
            ->where('defender_player_id', $player->id)
            ->latest()->limit(25)->get()
            ->map(fn ($b) => $this->battlePayload($b));

        return response()->json([
            'attacks'  => $attacks,
            'defenses' => $defenses,
            'revenge'  => $this->matchmaking->revengeTargets($player),
        ]);
    }

    /** Tekrar oynatma verisi (Unity savaşı burada canlandırır). */
    public function show(Request $request, Battle $battle): JsonResponse
    {
        $player = $request->attributes->get('player');

        if ($battle->attacker_player_id !== $player->id
            && $battle->defender_player_id !== $player->id) {
            throw GameException::izinYok('Bu savaş kaydına erişemezsin.');
        }

        return response()->json(['battle' => $this->battlePayload($battle, withReplay: true)]);
    }

    /** İntikam: daha önce saldıran oyuncuya karşı saldırı. */
    public function revenge(Request $request, Battle $battle): JsonResponse
    {
        $player = $request->attributes->get('player');

        if ($battle->defender_player_id !== $player->id) {
            throw GameException::izinYok('Bu savaş sana ait değil.');
        }

        $window = now()->subHours(config('game.battle.revenge_window_hours'));
        if ($battle->created_at->lt($window)) {
            throw new GameException('İntikam süresi doldu.', 'intikam_suresi');
        }

        $target = $battle->attacker?->village;
        if (! $target) {
            throw GameException::bulunamadi('Hedef oba bulunamadı.');
        }

        return response()->json(['target' => $this->villages->scout($target)]);
    }

    private function battlePayload(Battle $battle, bool $withReplay = false): array
    {
        $payload = [
            'id'          => $battle->id,
            'type'        => $battle->type,
            'stars'       => $battle->stars,
            'destruction' => (float) $battle->destruction_percent,
            'duration'    => $battle->duration_seconds,
            'loot'        => $battle->loot(),
            'trophies'    => [
                'attacker' => $battle->trophies_attacker,
                'defender' => $battle->trophies_defender,
            ],
            'army'        => $battle->army,
            'at'          => $battle->created_at->toIso8601String(),
            'attacker'    => $battle->relationLoaded('attacker') && $battle->attacker ? [
                'id' => $battle->attacker->id, 'name' => $battle->attacker->name,
                'trophies' => $battle->attacker->trophies,
            ] : ['id' => $battle->attacker_player_id],
            'defender'    => $battle->relationLoaded('defender') && $battle->defender ? [
                'id' => $battle->defender->id, 'name' => $battle->defender->name,
                'trophies' => $battle->defender->trophies,
            ] : ['id' => $battle->defender_player_id],
        ];

        if ($withReplay) {
            $payload['replay'] = $battle->replay;
            $payload['layout'] = $battle->layout;
        }

        return $payload;
    }
}
