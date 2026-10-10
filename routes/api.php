<?php

use App\Http\Controllers\Api\ArmyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BattleController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ClanController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\VillageController;
use App\Http\Controllers\Api\WarController;
use App\Http\Controllers\Api\WorldController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Klanlar Vadisi — Oyun API'si (v1)
|--------------------------------------------------------------------------
|
| Unity istemcisi yalnızca bu uçlarla konuşur. Bütün oyun kararları
| (kaynak üretimi, inşaat, eğitim, savaş sonucu) sunucuda verilir;
| istemci sonucu gösterir. Böylece hile yapılamaz.
|
| Kimlik doğrulama: Sanctum belirteci -> `Authorization: Bearer <token>`
|
*/

Route::prefix('v1')->group(function () {

    /* --------------------------- Genel bilgi --------------------------- */
    Route::get('durum', fn () => response()->json([
        'oyun'    => 'Klanlar Vadisi',
        'surum'   => config('game.version'),
        'sunucu'  => now()->toIso8601String(),
        'dunya'   => [
            'width'  => config('game.world.width'),
            'height' => config('game.world.height'),
        ],
    ]))->name('api.durum');

    Route::get('katalog', [CatalogController::class, 'index'])->name('api.katalog');

    /* ----------------------------- Giriş ------------------------------- */
    Route::post('kayit', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')->name('api.kayit');
    Route::post('giris', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')->name('api.giris');
    Route::post('konuk', [AuthController::class, 'guest'])
        ->middleware('throttle:10,1')->name('api.konuk');

    /* -------------------- Kimlik doğrulanmış uçlar --------------------- */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('ben', [AuthController::class, 'me'])->name('api.ben');
        Route::post('cikis', [AuthController::class, 'logout'])->name('api.cikis');

        Route::middleware('player')->group(function () {

            Route::patch('profil', [AuthController::class, 'updateProfile'])->name('api.profil');

            /* ------------------------- Oba ------------------------- */
            Route::prefix('oba')->group(function () {
                Route::get('/', [VillageController::class, 'show'])->name('api.oba');
                Route::post('bina', [VillageController::class, 'place'])->name('api.oba.bina');
                Route::post('bina/{building}/yukselt', [VillageController::class, 'upgrade'])->name('api.oba.yukselt');
                Route::post('bina/{building}/iptal', [VillageController::class, 'cancel'])->name('api.oba.iptal');
                Route::post('bina/{building}/tasi', [VillageController::class, 'move'])->name('api.oba.tasi');
                Route::post('bina/{building}/bitir', [VillageController::class, 'finish'])->name('api.oba.bitir');
                Route::delete('bina/{building}', [VillageController::class, 'destroy'])->name('api.oba.yik');
                Route::post('duzen', [VillageController::class, 'saveLayout'])->name('api.oba.duzen');
                Route::post('duzen/otomatik', [VillageController::class, 'autoLayout'])->name('api.oba.duzen.otomatik');
            });

            Route::get('oba/{village}/kesif', [VillageController::class, 'scout'])->name('api.oba.kesif');

            /* ------------------------ Ordu ------------------------- */
            Route::prefix('ordu')->group(function () {
                Route::post('egit', [ArmyController::class, 'train'])->name('api.ordu.egit');
                Route::delete('kuyruk/{item}', [ArmyController::class, 'cancelTraining'])->name('api.ordu.kuyruk.iptal');
                Route::post('tilsim', [ArmyController::class, 'brew'])->name('api.ordu.tilsim');
                Route::post('gelistir', [ArmyController::class, 'upgradeTroop'])->name('api.ordu.gelistir');
                Route::post('kahraman/uyandir', [ArmyController::class, 'unlockHero'])->name('api.ordu.kahraman.uyandir');
                Route::post('kahraman/gelistir', [ArmyController::class, 'upgradeHero'])->name('api.ordu.kahraman.gelistir');
            });

            /* ----------------------- Savaş ------------------------- */
            Route::prefix('savas')->group(function () {
                Route::post('rakip-ara', [BattleController::class, 'search'])
                    ->middleware('throttle:30,1')->name('api.savas.rakip');
                Route::post('saldir/{village}', [BattleController::class, 'attack'])
                    ->middleware('throttle:20,1')->name('api.savas.saldir');
                Route::get('gecmis', [BattleController::class, 'index'])->name('api.savas.gecmis');
                Route::get('{battle}', [BattleController::class, 'show'])->name('api.savas.goster');
                Route::post('{battle}/intikam', [BattleController::class, 'revenge'])->name('api.savas.intikam');
            });

            /* ----------------------- Dünya ------------------------- */
            Route::prefix('dunya')->group(function () {
                Route::get('bilgi', [WorldController::class, 'info'])->name('api.dunya.bilgi');
                Route::get('bolge', [WorldController::class, 'chunk'])->name('api.dunya.bolge');
                Route::get('kare', [WorldController::class, 'tile'])->name('api.dunya.kare');
            });

            /* ------------------------ Boy -------------------------- */
            Route::prefix('boy')->group(function () {
                Route::get('/', [ClanController::class, 'index'])->name('api.boy.liste');
                Route::post('/', [ClanController::class, 'store'])->name('api.boy.kur');
                Route::post('ayril', [ClanController::class, 'leave'])->name('api.boy.ayril');
                Route::get('basvurular', [ClanController::class, 'requests'])->name('api.boy.basvurular');
                Route::post('basvuru/{id}', [ClanController::class, 'respondRequest'])->name('api.boy.basvuru.yanit');
                Route::post('cikar/{member}', [ClanController::class, 'kick'])->name('api.boy.cikar');
                Route::post('rutbe/{member}', [ClanController::class, 'setRole'])->name('api.boy.rutbe');
                Route::post('baslik/{member}', [ClanController::class, 'transferLeadership'])->name('api.boy.baslik');

                Route::get('sohbet', [ClanController::class, 'chat'])->name('api.boy.sohbet');
                Route::post('sohbet', [ClanController::class, 'sendChat'])
                    ->middleware('throttle:30,1')->name('api.boy.sohbet.gonder');

                Route::post('asker-iste', [ClanController::class, 'requestTroops'])->name('api.boy.asker.iste');
                Route::get('asker-istekleri', [ClanController::class, 'openRequests'])->name('api.boy.asker.istekler');
                Route::post('bagis/{id}', [ClanController::class, 'donate'])->name('api.boy.bagis');

                Route::get('{clan}', [ClanController::class, 'show'])->name('api.boy.goster');
                Route::post('{clan}/katil', [ClanController::class, 'join'])->name('api.boy.katil');
            });

            /* -------------------- Boy savaşları -------------------- */
            Route::prefix('boy-savasi')->group(function () {
                Route::get('guncel', [WarController::class, 'current'])->name('api.savasi.guncel');
                Route::post('ilan', [WarController::class, 'declare'])->name('api.savasi.ilan');
                Route::get('gecmis', [WarController::class, 'history'])->name('api.savasi.gecmis');
                Route::get('{war}/hedefler', [WarController::class, 'targets'])->name('api.savasi.hedefler');
                Route::get('{war}/kesif/{defender}', [WarController::class, 'scout'])->name('api.savasi.kesif');
                Route::post('{war}/saldir/{defender}', [WarController::class, 'attack'])
                    ->middleware('throttle:20,1')->name('api.savasi.saldir');
            });

            /* ---------------------- Sıralama ----------------------- */
            Route::get('siralama/oyuncular', [LeaderboardController::class, 'players'])->name('api.siralama.oyuncular');
            Route::get('siralama/boylar', [LeaderboardController::class, 'clans'])->name('api.siralama.boylar');
        });
    });
});
