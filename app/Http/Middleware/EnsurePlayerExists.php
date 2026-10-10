<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kimliği doğrulanmış kullanıcının bir oyuncu profili (Player) olmasını
 * garanti eder ve isteğe `player` olarak ekler.
 */
class EnsurePlayerExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $player = $request->user()?->player;

        if (! $player) {
            return response()->json([
                'message' => 'Oyuncu profili bulunamadı. Önce bir oba kur.',
                'code'    => 'oyuncu_yok',
            ], 409);
        }

        if ($player->is_banned) {
            return response()->json([
                'message' => 'Hesabın askıya alınmış.',
                'code'    => 'yasakli',
            ], 403);
        }

        $player->forceFill(['last_seen_at' => now()])->saveQuietly();
        $request->attributes->set('player', $player);

        return $next($request);
    }
}
