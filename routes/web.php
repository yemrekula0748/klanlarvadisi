<?php

use App\Models\BuildingType;
use App\Models\Clan;
use App\Models\HeroType;
use App\Models\Player;
use App\Models\SpellType;
use App\Models\TroopType;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
|--------------------------------------------------------------------------
| Web
|--------------------------------------------------------------------------
|
| Oyunun istemcisi Unity'dir; burada tek bir karşılama/durum sayfası var.
| Sunucuyu yerelde çalıştıran kişi tarayıcıda ilk burayı açıyor, bu yüzden
| sayfa "sunucu ayakta mı, sırada ne var" sorularını yanıtlar.
|
*/

Route::get('/', function () {
    // Veritabanı henüz göç edilmemiş olabilir; sayfa o durumda da açılmalı.
    try {
        $sayilar = [
            'bina'   => BuildingType::count(),
            'asker'  => TroopType::count(),
            'alp'    => HeroType::count(),
            'buyu'   => SpellType::count(),
            'oyuncu' => Player::count(),
            'oba'    => Village::count(),
            'boy'    => Clan::count(),
        ];
        $hazir = $sayilar['bina'] > 0;
    } catch (\Throwable) {
        $sayilar = null;
        $hazir = false;
    }

    // Örnek giriş bilgisini uydurmak yerine gerçekten var olan ilk demo
    // oyuncusundan okuyoruz.
    $demo = null;
    try {
        $demo = User::whereLike('email', '%@klanlarvadisi.test')->orderBy('id')->value('email');
    } catch (\Throwable) {
        // önemsiz
    }

    return view('welcome', [
        'sayilar'      => $sayilar,
        'hazir'        => $hazir,
        'demoEposta'   => $demo,
        'demoSifre'    => 'sifre1234',
        'reverbPort'   => config('broadcasting.connections.reverb.options.port'),
        'reverbAnahtar' => config('broadcasting.connections.reverb.key'),
        'onizlemeler'  => collect([
            'oba'            => 'Oba görünümü',
            'dunya-bolgeler' => 'Dünya haritası ve bölgeler',
            'dunya-yakin'    => 'Arazi yakın plan',
        ])->filter(fn ($_, $ad) => is_file(base_path("docs/onizleme/{$ad}.png"))),
    ]);
});

/**
 * Önizleme görselleri depoda docs/onizleme altında durur (tek kopya);
 * karşılama sayfası onları buradan okur.
 */
Route::get('/onizleme/{ad}', function (string $ad) {
    abort_unless(preg_match('/^[a-z0-9-]+$/', $ad), 404);

    $yol = base_path("docs/onizleme/{$ad}.png");
    abort_unless(is_file($yol), 404);

    return new BinaryFileResponse($yol, 200, ['Cache-Control' => 'public, max-age=300']);
})->name('onizleme');
