<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GameException;
use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\ClanWar;
use App\Models\Player;
use App\Services\ClanWarService;
use App\Services\VillageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarController extends Controller
{
    public function __construct(
        private readonly ClanWarService $wars,
        private readonly VillageService $villages,
    ) {}

    /** Boyun güncel savaşı. */
    public function current(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');
        if (! $player->clan_id) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        $war = $player->clan->currentWar();
        if (! $war) {
            return response()->json(['war' => null]);
        }

        return response()->json(['war' => $this->warPayload($war, $player)]);
    }

    /** Savaş ilan et (yalnızca boy başı / vezir). */
    public function declare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'opponent_clan_id' => ['required', 'integer', 'exists:clans,id'],
            'size'             => ['required', 'integer'],
        ]);

        $player = $request->attributes->get('player');
        if (! $player->canManageClan()) {
            throw GameException::izinYok('Savaş ilan etmek için vezir olmalısın.');
        }

        $opponent = Clan::findOr(
            $data['opponent_clan_id'],
            fn () => throw GameException::bulunamadi('Rakip boy bulunamadı.')
        );

        $war = $this->wars->declare($player->clan, $opponent, $data['size']);

        return response()->json(['war' => $this->warPayload($war, $player)], 201);
    }

    /** Savaş haritasındaki rakip obalar. */
    public function targets(Request $request, ClanWar $war): JsonResponse
    {
        $player = $request->attributes->get('player');

        return response()->json(['targets' => $this->wars->targetsFor($war, $player)]);
    }

    /** Savaş düzenini görüntüle (hazırlık günü dondurulmuş düzen). */
    public function scout(Request $request, ClanWar $war, Player $defender): JsonResponse
    {
        $player = $request->attributes->get('player');
        $me = $war->members()->where('player_id', $player->id)->first();
        $enemy = $war->members()->where('player_id', $defender->id)->first();

        if (! $me || ! $enemy) {
            throw GameException::izinYok('Bu savaşta yer almıyorsun.');
        }
        if ($me->clan_id === $enemy->clan_id) {
            throw GameException::izinYok('Kendi boyunun düzenini keşfe gerek yok.');
        }

        return response()->json([
            'defender' => ['id' => $defender->id, 'name' => $defender->name],
            'position' => $enemy->map_position,
            'stars'    => $enemy->best_stars_against,
            'layout'   => $enemy->layout,
        ]);
    }

    /** Savaş saldırısı. */
    public function attack(Request $request, ClanWar $war, Player $defender): JsonResponse
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
        ]);

        $player = $request->attributes->get('player');

        $battle = $this->wars->attack(
            $player,
            $war,
            $defender,
            $data['deployments'],
            $data['spells'] ?? [],
            $data['heroes'] ?? []
        );

        return response()->json([
            'battle' => [
                'id'          => $battle->id,
                'stars'       => $battle->stars,
                'destruction' => (float) $battle->destruction_percent,
                'duration'    => $battle->duration_seconds,
                'replay'      => $battle->replay,
            ],
            'war' => $this->warPayload($war->refresh(), $player),
        ], 201);
    }

    /** Savaş geçmişi. */
    public function history(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');
        if (! $player->clan_id) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        $wars = ClanWar::with(['clanA:id,name,tag,badge', 'clanB:id,name,tag,badge'])
            ->where(fn ($q) => $q->where('clan_a_id', $player->clan_id)->orWhere('clan_b_id', $player->clan_id))
            ->where('state', 'bitti')
            ->latest()->limit(20)->get();

        return response()->json([
            'wars' => $wars->map(fn (ClanWar $w) => [
                'id'      => $w->id,
                'size'    => $w->size,
                'us'      => $w->clan_a_id === $player->clan_id
                    ? ['stars' => $w->stars_a, 'destruction' => (float) $w->destruction_a]
                    : ['stars' => $w->stars_b, 'destruction' => (float) $w->destruction_b],
                'them'    => $w->clan_a_id === $player->clan_id
                    ? ['stars' => $w->stars_b, 'destruction' => (float) $w->destruction_b, 'clan' => $w->clanB?->only(['id', 'name', 'tag', 'badge'])]
                    : ['stars' => $w->stars_a, 'destruction' => (float) $w->destruction_a, 'clan' => $w->clanA?->only(['id', 'name', 'tag', 'badge'])],
                'won'     => $w->winner_clan_id === $player->clan_id,
                'ended'   => $w->battle_ends_at->toIso8601String(),
            ])->all(),
        ]);
    }

    private function warPayload(ClanWar $war, Player $player): array
    {
        $war->loadMissing(['clanA:id,name,tag,badge,level', 'clanB:id,name,tag,badge,level', 'members.player:id,name,trophies']);
        $mine = $war->clan_a_id === $player->clan_id;

        $side = fn (string $which) => [
            'clan'        => $which === 'a' ? $war->clanA?->only(['id', 'name', 'tag', 'badge', 'level'])
                                            : $war->clanB?->only(['id', 'name', 'tag', 'badge', 'level']),
            'stars'       => $which === 'a' ? $war->stars_a : $war->stars_b,
            'destruction' => (float) ($which === 'a' ? $war->destruction_a : $war->destruction_b),
            'members'     => $war->members
                ->where('clan_id', $which === 'a' ? $war->clan_a_id : $war->clan_b_id)
                ->sortBy('map_position')
                ->map(fn ($m) => [
                    'position'    => $m->map_position,
                    'player'      => ['id' => $m->player_id, 'name' => $m->player?->name, 'trophies' => $m->player?->trophies],
                    'attacks'     => $m->attacks_used,
                    'stars'       => $m->stars_earned,
                    'taken'       => $m->best_stars_against,
                    'destruction' => (float) $m->best_destruction_against,
                ])->values()->all(),
        ];

        return [
            'id'              => $war->id,
            'size'            => $war->size,
            'state'           => $war->state,
            'prep_ends_at'    => $war->prep_ends_at->toIso8601String(),
            'battle_ends_at'  => $war->battle_ends_at->toIso8601String(),
            'attacks_allowed' => config('game.clan.war_attacks_per_member'),
            'us'              => $side($mine ? 'a' : 'b'),
            'them'            => $side($mine ? 'b' : 'a'),
            'winner'          => $war->winner_clan_id,
        ];
    }
}
