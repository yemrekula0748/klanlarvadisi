<?php

use App\Services\ClanWarService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Zamanlanmış Görevler
|--------------------------------------------------------------------------
|
| Kaynak üretimi ve inşaat/eğitim tamamlanması TEMBEL hesaplanır (okuma
| anında), bu yüzden milyonlarca oba için arka plan işi gerekmez. Burada
| yalnızca gerçekten zamana bağlı işler çalışır.
|
| Üretimde tek bir cron yeterlidir:
|   * * * * * cd /yol/klanlarvadisi && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Boy savaşları: hazırlık -> savaş -> sonuç geçişleri.
Schedule::call(function (ClanWarService $wars) {
    $result = $wars->tick();
    if ($result['basladi'] || $result['bitti']) {
        logger()->info('Boy savaşı geçişleri', $result);
    }
})->everyFiveMinutes()->name('boy-savasi-tik')->withoutOverlapping();

// Süresi geçmiş kalkanları temizle (istemci yanlış bilgi görmesin).
Schedule::call(function () {
    \App\Models\Player::whereNotNull('shield_until')
        ->where('shield_until', '<=', now())
        ->update(['shield_until' => null]);

    \App\Models\Player::whereNotNull('guard_until')
        ->where('guard_until', '<=', now())
        ->update(['guard_until' => null]);
})->hourly()->name('kalkan-temizligi');

// Süresi geçmiş asker isteklerini sil.
Schedule::call(function () {
    \App\Models\TroopRequest::where('expires_at', '<=', now()->subDay())->delete();
})->daily()->name('asker-istek-temizligi');

// Eski savaş tekrarlarını küçült: 30 günden eski kayıtların replay'i silinir
// (sonuç ve ganimet bilgisi kalır). Tekrar dosyaları yer kaplar.
Schedule::call(function () {
    \App\Models\Battle::whereNotNull('replay')
        ->where('created_at', '<=', now()->subDays(30))
        ->update(['replay' => null]);
})->daily()->name('tekrar-temizligi');

// Sıralama önbelleğini tazele.
Schedule::call(function () {
    cache()->forget('siralama:oyuncular');
    cache()->forget('siralama:boylar');
})->everyMinute()->name('siralama-tazeleme');
