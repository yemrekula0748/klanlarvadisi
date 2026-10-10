<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clan;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    public function players(Request $request): JsonResponse
    {
        $rows = Cache::remember('siralama:oyuncular', 60, fn () => Player::with('clan:id,name,tag,badge')
            ->where('is_banned', false)
            ->orderByDesc('trophies')
            ->limit(200)
            ->get()
            ->values()
            ->map(fn (Player $p, int $i) => [
                'rank'     => $i + 1,
                'id'       => $p->id,
                'name'     => $p->name,
                'avatar'   => $p->avatar,
                'level'    => $p->level,
                'trophies' => $p->trophies,
                'league'   => $p->league(),
                'clan'     => $p->clan?->only(['id', 'name', 'tag', 'badge']),
            ])->all());

        $player = $request->attributes->get('player');
        $myRank = $player
            ? Player::where('is_banned', false)->where('trophies', '>', $player->trophies)->count() + 1
            : null;

        return response()->json(['players' => $rows, 'my_rank' => $myRank]);
    }

    public function clans(): JsonResponse
    {
        $rows = Cache::remember('siralama:boylar', 60, fn () => Clan::orderByDesc('points')
            ->limit(200)->get()->values()
            ->map(fn (Clan $c, int $i) => [
                'rank'      => $i + 1,
                'id'        => $c->id,
                'name'      => $c->name,
                'tag'       => $c->tag,
                'badge'     => $c->badge,
                'level'     => $c->level,
                'points'    => (int) $c->points,
                'members'   => $c->member_count,
                'war_wins'  => $c->war_wins,
            ])->all());

        return response()->json(['clans' => $rows]);
    }
}
