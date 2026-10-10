<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    // Yayın kanalları. `/broadcasting/auth` varsayılan olarak `web` ara
    // katmanıyla (oturum çerezi) kurulur; Unity istemcisi ise Sanctum
    // belirteci taşır. Bu yüzden kanalları elle, `auth:sanctum` ile
    // kaydediyoruz — aksi halde özel kanal yetkisi hep 403 döner.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Unity istemcisinin oyuncu profili olmasını garanti eden ara katman.
        $middleware->alias([
            'player'    => \App\Http\Middleware\EnsurePlayerExists::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);

        // Ters vekil (nginx / yük dengeleyici) arkasında doğru IP ve şema.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Doğrulama özeti: Laravel'in üst düzey `message` alanına eklediği
        // "(and 2 more errors)" metni çeviriye kapalıdır. Unity istemcisi bu
        // alanı doğrudan oyuncuya gösterdiği için Türkçe üretiyoruz.
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $hatalar = $e->validator->errors();
            $kalan   = $hatalar->count() - 1;

            return response()->json([
                'message' => $kalan > 0
                    ? $hatalar->first()." (+{$kalan} hata daha)"
                    : $hatalar->first(),
                'code'    => 'dogrulama',
                'errors'  => $hatalar->messages(),
            ], 422);
        });

        $exceptions->render(function (\App\Exceptions\GameException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => $e->errorCode,
            ], $e->status);
        });
    })->create();
