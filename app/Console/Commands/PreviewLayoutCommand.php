<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\BuildingType;
use App\Models\Player;
use App\Models\User;
use App\Models\Village;
use App\Services\LayoutGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Gerçek yerleşim algoritmasını (App\Services\LayoutGenerator) çalıştırıp
 * sonucu JSON olarak basar. Unity kurmadan oba görünümünü üretmek için
 * `tools/preview/oba.mjs` bu komutun çıktısını kullanır.
 *
 *   php artisan game:onizleme-duzen --otag=10
 *   php artisan game:onizleme-duzen --otag=14 --okunur
 *
 * VERİTABANINA YAZMAZ. Geçici bir oba işlem (transaction) içinde kurulur,
 * düzen okunur, ardından işlem geri alınır. Komut, öncesi/sonrası satır
 * sayılarını karşılaştırır; eşit değilse HATA döner.
 *
 * JSON stdout'a, bütün tanı iletileri stderr'e yazılır — böylece çıktı
 * doğrudan bir boruya (pipe) verilebilir.
 */
class PreviewLayoutCommand extends Command
{
    protected $signature = 'game:onizleme-duzen
        {--otag=10 : Otağ seviyesi (1-15)}
        {--okunur : JSON\'u girintili bas}';

    protected $description = 'Verilen Otağ seviyesinde gerçek bir oba yerleşimi üretip JSON olarak basar (veritabanına yazmaz)';

    public function handle(LayoutGenerator $duzenUretici): int
    {
        $otagSeviyesi = (int) $this->option('otag');

        if ($otagSeviyesi < 1 || $otagSeviyesi > 15) {
            $this->tani()->writeln("<error>Otağ seviyesi 1-15 arasında olmalı, verilen: {$otagSeviyesi}</error>");

            return self::FAILURE;
        }

        if (BuildingType::count() === 0) {
            $this->tani()->writeln('<error>Bina kataloğu boş. Önce: php artisan db:seed --class=CatalogSeeder</error>');

            return self::FAILURE;
        }

        $oncesi = $this->satirSayilari();

        DB::beginTransaction();

        try {
            $oba = $this->geciciObaKur();
            $istatistik = $duzenUretici->build($oba, $otagSeviyesi);
            $binalar = $this->binalariOku($oba);
        } finally {
            // Her durumda geri al — başarıda da, istisnada da.
            DB::rollBack();
        }

        $sonrasi = $this->satirSayilari();

        if ($oncesi !== $sonrasi) {
            $this->tani()->writeln('<error>Veritabanı değişti! Geri alma başarısız.</error>');
            $this->satirSayilariniBas($oncesi, $sonrasi);

            return self::FAILURE;
        }

        $cakismalar = $this->cakismalariBul($binalar, (int) config('game.grid.size'));

        $this->satirSayilariniBas($oncesi, $sonrasi);
        $this->tani()->writeln(sprintf(
            '  Otağ %d  ->  %d bina (%d sur, %d savunma), %d çakışma',
            $otagSeviyesi,
            count($binalar),
            $istatistik['walls'],
            $istatistik['defenses'],
            count($cakismalar)
        ));

        foreach (array_slice($cakismalar, 0, 20) as $c) {
            $this->tani()->writeln("  <comment>çakışma</comment> ({$c['x']},{$c['y']}): {$c['a']} / {$c['b']}");
        }

        $this->line(json_encode([
            'grid' => (int) config('game.grid.size'),
            'margin' => (int) config('game.grid.margin'),
            'otag' => $otagSeviyesi,
            'stats' => $istatistik,
            'overlaps' => $cakismalar,
            'buildings' => $binalar,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | ($this->option('okunur') ? JSON_PRETTY_PRINT : 0)));

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Düzenin kurulacağı geçici oba. Mevcut bir oyuncuya bağlanır; hiç
     * oyuncu yoksa geçici bir kullanıcı/oyuncu da açılır. Hepsi işlem
     * geri alınırken yok olur.
     */
    private function geciciObaKur(): Village
    {
        $oyuncu = Player::query()->orderBy('id')->first();

        if (! $oyuncu) {
            $kullanici = User::create([
                'name' => 'Önizleme',
                'email' => 'onizleme-'.Str::random(10).'@klanlarvadisi.test',
                'password' => bcrypt(Str::random(24)),
            ]);

            $oyuncu = Player::create([
                'user_id' => $kullanici->id,
                'name' => 'onizleme_'.Str::random(8),
            ]);
        }

        // world_x/world_y tekil bir çift olmak zorunda. Mevcut en büyük
        // değerin bir fazlasını almak çakışmayı imkânsız kılar.
        return Village::create([
            'player_id' => $oyuncu->id,
            'name' => 'Önizleme Obası',
            'world_x' => (int) (Village::max('world_x') ?? 0) + 1,
            'world_y' => (int) (Village::max('world_y') ?? 0) + 1,
            'biome' => 'cayir',
            'otag_level' => 1,
            'is_capital' => false,
            'resources_updated_at' => now(),
        ]);
    }

    /**
     * Kurulan binaları çizim sırasına göre döndürür.
     *
     * Derinlik sırası Unity'deki `BinaGorunumu.Guncelle` ile birebir aynı:
     * ayak izinin MERKEZİ (x + g/2, y + g/2) sıralama anahtarıdır.
     *
     * @return array<int,array{key:string,x:int,y:int,grid:int,grid_h:int,level:int,category:string}>
     */
    private function binalariOku(Village $oba): array
    {
        $binalar = Building::with('type')
            ->where('village_id', $oba->id)
            ->get()
            ->map(function (Building $bina) {
                if ($bina->type->grid_w !== $bina->type->grid_h) {
                    $this->tani()->writeln(
                        "  <comment>uyarı</comment> {$bina->type->key} ayak izi kare değil: "
                        ."{$bina->type->grid_w}x{$bina->type->grid_h}"
                    );
                }

                return [
                    'key' => $bina->type->key,
                    'x' => (int) $bina->x,
                    'y' => (int) $bina->y,
                    'grid' => (int) $bina->type->grid_w,
                    'grid_h' => (int) $bina->type->grid_h,
                    'level' => (int) $bina->level,
                    'category' => (string) $bina->type->category,
                ];
            })
            ->all();

        usort($binalar, function (array $a, array $b) {
            $da = $a['x'] + $a['y'] + $a['grid'];   // merkez derinliği
            $db = $b['x'] + $b['y'] + $b['grid'];

            return [$da, $a['x']] <=> [$db, $b['x']];
        });

        return $binalar;
    }

    /**
     * Aynı kareyi paylaşan bina çiftlerini bulur. LayoutGenerator çakışmasız
     * yerleşim üretmeli; bu liste boş değilse hata sunucudadır.
     *
     * @param  array<int,array<string,mixed>>  $binalar
     * @return array<int,array{x:int,y:int,a:string,b:string}>
     */
    private function cakismalariBul(array $binalar, int $izgara): array
    {
        $dolu = [];
        $cakismalar = [];

        foreach ($binalar as $bina) {
            for ($dy = 0; $dy < $bina['grid_h']; $dy++) {
                for ($dx = 0; $dx < $bina['grid']; $dx++) {
                    $kare = ($bina['x'] + $dx).','.($bina['y'] + $dy);

                    if (isset($dolu[$kare])) {
                        $cakismalar[] = [
                            'x' => $bina['x'] + $dx,
                            'y' => $bina['y'] + $dy,
                            'a' => $dolu[$kare],
                            'b' => $bina['key'],
                        ];
                    }

                    $dolu[$kare] = $bina['key'];
                }
            }

            // Izgara dışına taşma da bir kusurdur.
            if ($bina['x'] < 0 || $bina['y'] < 0
                || $izgara < $bina['x'] + $bina['grid']
                || $izgara < $bina['y'] + $bina['grid_h']) {
                $cakismalar[] = [
                    'x' => $bina['x'],
                    'y' => $bina['y'],
                    'a' => 'IZGARA_DISI',
                    'b' => $bina['key'],
                ];
            }
        }

        return $cakismalar;
    }

    /** @return array<string,int> */
    private function satirSayilari(): array
    {
        return [
            'villages' => Village::count(),
            'buildings' => Building::count(),
            'players' => Player::count(),
            'users' => User::count(),
        ];
    }

    /**
     * @param  array<string,int>  $oncesi
     * @param  array<string,int>  $sonrasi
     */
    private function satirSayilariniBas(array $oncesi, array $sonrasi): void
    {
        $satirlar = [];
        foreach ($oncesi as $tablo => $sayi) {
            $satirlar[] = sprintf('%s %d->%d', $tablo, $sayi, $sonrasi[$tablo] ?? -1);
        }

        $this->tani()->writeln('  DB (öncesi->sonrası): '.implode('  ', $satirlar));
    }

    /** Tanı iletileri stderr'e gider; stdout yalnızca JSON taşır. */
    private function tani(): OutputInterface
    {
        return $this->output->getErrorStyle();
    }
}
