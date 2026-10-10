<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GameException;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Clan;
use App\Models\Player;
use App\Models\TroopRequest;
use App\Services\ClanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClanController extends Controller
{
    public function __construct(private readonly ClanService $clans) {}

    /** Boy ara / listele. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:40'],
            'min'      => ['nullable', 'integer', 'min:0'],
            'type'     => ['nullable', 'string', 'in:acik,davet,kapali'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Clan::query()
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(function ($s) use ($term) {
                $s->where('name', 'like', "%{$term}%")->orWhere('tag', 'like', "%{$term}%");
            }))
            ->when($data['min'] ?? null, fn ($q, $min) => $q->where('required_trophies', '>=', $min))
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('points');

        return response()->json(
            $query->paginate(20)->through(fn (Clan $c) => $this->clanPayload($c))
        );
    }

    public function show(Clan $clan): JsonResponse
    {
        return response()->json([
            'clan'    => $this->clanPayload($clan, detailed: true),
            'members' => $clan->members()->get()->map(fn (Player $p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'avatar'    => $p->avatar,
                'level'     => $p->level,
                'trophies'  => $p->trophies,
                'role'      => $p->clan_role,
                'donated'   => $p->donated,
                'received'  => $p->received,
                'war_stars' => $p->war_stars,
                'online'    => $p->last_seen_at && $p->last_seen_at->gt(now()->subMinutes(5)),
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string',
                'min:'.config('game.clan.name_min'), 'max:'.config('game.clan.name_max'),
                'unique:clans,name'],
            'description'       => ['nullable', 'string', 'max:400'],
            'type'              => ['nullable', 'string', 'in:acik,davet,kapali'],
            'required_trophies' => ['nullable', 'integer', 'min:0', 'max:6000'],
            'badge'             => ['nullable', 'array'],
            'badge.shape'       => ['nullable', 'string', 'max:24'],
            'badge.symbol'      => ['nullable', 'string', 'max:24'],
            'badge.colors'      => ['nullable', 'array', 'max:3'],
            'badge.colors.*'    => ['string', 'max:9'],
        ]);

        $player = $request->attributes->get('player');

        // Boy kurma bedeli: 40.000 altın
        $village = $player->village;
        app(\App\Services\ResourceService::class)->sync($village);
        app(\App\Services\ResourceService::class)->spend($village, ['gold' => 40000]);

        $clan = $this->clans->create($player, $data);

        return response()->json(['clan' => $this->clanPayload($clan, detailed: true)], 201);
    }

    public function join(Request $request, Clan $clan): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:200']]);
        $player = $request->attributes->get('player');

        $result = $this->clans->join($player, $clan, $data['message'] ?? '');

        return response()->json($result + ['clan' => $this->clanPayload($clan->refresh())]);
    }

    public function leave(Request $request): JsonResponse
    {
        $this->clans->leave($request->attributes->get('player'));

        return response()->json(['message' => 'Boydan ayrıldın.']);
    }

    public function requests(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');
        if (! $player->canManageClan()) {
            throw GameException::izinYok('Başvuruları görmek için vezir olmalısın.');
        }

        return response()->json([
            'requests' => $player->clan->joinRequests()->with('player:id,name,trophies,level')->get()
                ->map(fn ($r) => [
                    'id'      => $r->id,
                    'message' => $r->message,
                    'player'  => [
                        'id' => $r->player->id, 'name' => $r->player->name,
                        'trophies' => $r->player->trophies, 'level' => $r->player->level,
                    ],
                    'at' => $r->created_at->toIso8601String(),
                ])->all(),
        ]);
    }

    public function respondRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $player = $request->attributes->get('player');

        if (! $player->canManageClan()) {
            throw GameException::izinYok('Bunun için vezir olmalısın.');
        }

        $joinRequest = $player->clan->joinRequests()->findOr(
            $id, fn () => throw GameException::bulunamadi('Başvuru bulunamadı.')
        );

        if ($data['accept']) {
            $this->clans->addMember($joinRequest->player, $player->clan);
        } else {
            $joinRequest->update(['status' => 'red']);
        }

        return response()->json(['message' => $data['accept'] ? 'Üye alındı.' : 'Başvuru reddedildi.']);
    }

    public function kick(Request $request, Player $member): JsonResponse
    {
        $this->clans->kick($request->attributes->get('player'), $member);

        return response()->json(['message' => "{$member->name} boydan çıkarıldı."]);
    }

    public function setRole(Request $request, Player $member): JsonResponse
    {
        $data = $request->validate(['role' => ['required', 'string', 'in:vezir,alp,er']]);
        $this->clans->setRole($request->attributes->get('player'), $member, $data['role']);

        return response()->json(['message' => 'Rütbe güncellendi.']);
    }

    public function transferLeadership(Request $request, Player $member): JsonResponse
    {
        $this->clans->transferLeadership($request->attributes->get('player'), $member);

        return response()->json(['message' => "Boy başlığı {$member->name}'e devredildi."]);
    }

    /* ------------------------------ Bağış ------------------------------ */

    public function requestTroops(Request $request): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:200']]);
        $req = $this->clans->requestTroops($request->attributes->get('player'), $data['message'] ?? '');

        return response()->json(['request' => [
            'id'         => $req->id,
            'capacity'   => $req->capacity,
            'fulfilled'  => $req->fulfilled,
            'expires_at' => $req->expires_at->toIso8601String(),
        ]], 201);
    }

    public function openRequests(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');
        if (! $player->clan_id) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        return response()->json([
            'requests' => TroopRequest::with('player:id,name,avatar')
                ->where('clan_id', $player->clan_id)
                ->where('expires_at', '>', now())
                ->whereColumn('fulfilled', '<', 'capacity')
                ->latest()->get()
                ->map(fn ($r) => [
                    'id'        => $r->id,
                    'message'   => $r->message,
                    'capacity'  => $r->capacity,
                    'fulfilled' => $r->fulfilled,
                    'player'    => ['id' => $r->player->id, 'name' => $r->player->name],
                    'at'        => $r->created_at->toIso8601String(),
                ])->all(),
        ]);
    }

    public function donate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'troop'    => ['required', 'string', 'max:48'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $player = $request->attributes->get('player');
        $troopRequest = TroopRequest::findOr($id, fn () => throw GameException::bulunamadi('İstek bulunamadı.'));

        $this->clans->donate($player, $troopRequest, $data['troop'], $data['quantity']);

        return response()->json(['message' => 'Asker gönderildi.']);
    }

    /* ------------------------------- Sohbet ---------------------------- */

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $player = $request->attributes->get('player');
        if (! $player->clan_id) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        $messages = ChatMessage::with('player:id,name,avatar')
            ->where('scope', 'boy')
            ->where('clan_id', $player->clan_id)
            ->when($data['after'] ?? null, fn ($q, $after) => $q->where('id', '>', $after))
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'messages' => $messages->map(fn ($m) => [
                'id'     => $m->id,
                'kind'   => $m->kind,
                'body'   => $m->body,
                'meta'   => $m->meta,
                'player' => $m->player ? [
                    'id' => $m->player->id, 'name' => $m->player->name, 'avatar' => $m->player->avatar,
                ] : null,
                'at' => $m->created_at->toIso8601String(),
            ])->all(),
        ]);
    }

    public function sendChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:300'],
        ]);

        $player = $request->attributes->get('player');
        if (! $player->clan_id) {
            throw new GameException('Bir boya bağlı değilsin.', 'boy_yok');
        }

        $message = ChatMessage::create([
            'scope'     => 'boy',
            'clan_id'   => $player->clan_id,
            'player_id' => $player->id,
            'kind'      => 'mesaj',
            'body'      => $data['body'],
        ]);

        broadcast(new \App\Events\ClanChatMessage($message))->toOthers();

        return response()->json(['id' => $message->id], 201);
    }

    private function clanPayload(Clan $clan, bool $detailed = false): array
    {
        $payload = [
            'id'                => $clan->id,
            'name'              => $clan->name,
            'tag'               => $clan->tag,
            'badge'             => $clan->badge,
            'type'              => $clan->type,
            'level'             => $clan->level,
            'points'            => (int) $clan->points,
            'members'           => $clan->member_count,
            'member_limit'      => $clan->member_limit,
            'required_trophies' => $clan->required_trophies,
            'war_wins'          => $clan->war_wins,
            'war_streak'        => $clan->war_streak,
        ];

        if ($detailed) {
            $payload['description'] = $clan->description;
            $payload['war_losses'] = $clan->war_losses;
            $payload['xp'] = (int) $clan->xp;
            $payload['leader'] = $clan->leader?->only(['id', 'name']);
        }

        return $payload;
    }
}
