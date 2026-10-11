<?php

namespace App\Console\Commands;

use App\Services\WorldService;
use GdImage;
use Illuminate\Console\Command;

/**
 * Dünya haritasının önizleme görsellerini üretir.
 *
 * Arazi BU KOMUTTA ÜRETİLMEZ: her kare `App\Services\WorldService::terrainAt()`
 * ile sorulur. Yani görselde ne görüyorsanız Unity istemcisinin çizdiği dünya
 * odur — `WorldTerrain.Ornekle()` birebir aynı `Noise` matematiğini kullanır.
 * Harita bozuksa kusur burada değil, `WorldService` ya da `config/regions.php`
 * içindedir.
 *
 *   php artisan game:onizleme-dunya
 *   php artisan game:onizleme-dunya --adim=4 --bolge=otuken    (hızlı deneme)
 *
 * Üretilenler (varsayılan docs/onizleme/):
 *   dunya.png           tüm dünya, 1 piksel = 1 kare
 *   dunya-bolgeler.png  aynı harita + 16 bölgenin sınırı, adı ve lejant
 *   dunya-yakin.png     tek bir bölgenin yakın planı
 *
 * VERİTABANINA DOKUNMAZ; yalnızca arazi matematiği çalıştırılır.
 */
class PreviewWorldCommand extends Command
{
    protected $signature = 'game:onizleme-dunya
        {--dizin= : PNG çıktı klasörü (varsayılan: docs/onizleme)}
        {--adim=1 : Kaç karede bir örnek alınır (2 = yarı çözünürlük, dört kat hızlı)}
        {--olcek=1 : Dünya haritasında örnek başına piksel}
        {--bolge=ergenekon : Yakın plan çekilecek bölgenin anahtarı}
        {--yakin=200 : Yakın planın kenar uzunluğu (kare)}
        {--yakin-olcek=4 : Yakın planda kare başına piksel}
        {--font= : Kullanılacak TTF/OTF yolu (boşsa sistemde aranır)}
        {--kati : Bölge-arazi uyuşmazlığında hata koduyla çık (CI için)}';

    protected $description = 'Dünya haritasını gerçek arazi kodundan çizip PNG önizlemeleri üretir';

    /**
     * Biyom paleti. Anahtarlar `WorldService::BIOMES` ile aynı olmak
     * zorundadır; eksik bir anahtar komutu hata ile durdurur.
     *
     * Her biyom iki tonla boyanır: `degisken` alanındaki arazi değeri
     * `alt`..`ust` aralığında nereye düşüyorsa ton oradan karıştırılır.
     * Böylece aynı biyomun içi tek bir renk lekesi olmaz. `kabartma`
     * o biyomda tepe gölgesinin ne kadar sert vurduğudur (su düz görünsün
     * diye denizde düşük, dağda yüksek).
     */
    private const PALET = [
        'deniz' => ['dusuk' => '#082741', 'yuksek' => '#1e5e85', 'degisken' => 'yukseklik', 'alt' => 0.00, 'ust' => 0.17, 'kabartma' => 0.22],
        'kiyi' => ['dusuk' => '#cbb688', 'yuksek' => '#f0e4bd', 'degisken' => 'yukseklik', 'alt' => 0.17, 'ust' => 0.21, 'kabartma' => 0.55],
        'nehir' => ['dusuk' => '#2a6b92', 'yuksek' => '#5aaccf', 'degisken' => 'yukseklik', 'alt' => 0.20, 'ust' => 0.70, 'kabartma' => 0.30],
        'bataklik' => ['dusuk' => '#3c4a29', 'yuksek' => '#6d7848', 'degisken' => 'nem', 'alt' => 0.55, 'ust' => 1.00, 'kabartma' => 0.70],
        'cayir' => ['dusuk' => '#56893a', 'yuksek' => '#93c067', 'degisken' => 'nem', 'alt' => 0.40, 'ust' => 0.70, 'kabartma' => 1.00],
        'bozkir' => ['dusuk' => '#879552', 'yuksek' => '#c3c689', 'degisken' => 'nem', 'alt' => 0.12, 'ust' => 0.60, 'kabartma' => 1.00],
        'orman' => ['dusuk' => '#1b4026', 'yuksek' => '#3d7347', 'degisken' => 'nem', 'alt' => 0.60, 'ust' => 1.00, 'kabartma' => 1.05],
        'tayga' => ['dusuk' => '#234c3a', 'yuksek' => '#4d8465', 'degisken' => 'nem', 'alt' => 0.45, 'ust' => 0.95, 'kabartma' => 1.05],
        'col' => ['dusuk' => '#b4723a', 'yuksek' => '#ebc084', 'degisken' => 'sicaklik', 'alt' => 0.58, 'ust' => 1.00, 'kabartma' => 0.90],
        'dag' => ['dusuk' => '#695e51', 'yuksek' => '#a2988a', 'degisken' => 'yukseklik', 'alt' => 0.70, 'ust' => 0.87, 'kabartma' => 1.15],
        'kar' => ['dusuk' => '#c6d6e4', 'yuksek' => '#fbfdff', 'degisken' => 'yukseklik', 'alt' => 0.87, 'ust' => 1.00, 'kabartma' => 1.05],
    ];

    /** Yakın planda iki eş yükselti eğrisi arası (0..1 yükseklik birimi). */
    private const EGRI_ARALIGI = 0.01;

    /** Lejant/başlık panelinin renkleri. */
    private const PANEL_ZEMIN = '#12161d';

    private const PANEL_CIZGI = '#2a323e';

    private const PANEL_YAZI = '#e6ebf2';

    private const PANEL_SOLUK = '#93a0b2';

    private string $font;

    /**
     * Tipografi ölçeği. Tüm punto ve boşluklar bununla çarpılır; böylece
     * `--adim=8` ile çizilen küçük taslakta da yazılar haritayla orantılı
     * kalır. 1.0 = tam boy (1600 piksel) harita.
     */
    private float $birim = 1.0;

    public function handle(WorldService $dunya): int
    {
        $font = $this->fontBul();

        if ($font === null) {
            $this->error('Türkçe çizebilen bir TTF/OTF bulunamadı.');
            $this->line('  Denenenler: '.implode(', ', $this->fontAdaylari()));
            $this->line('  Çözüm: `apt-get install fonts-dejavu-core` ya da --font=/yol/font.ttf');
            $this->line('  (Etiketler ASCII\'ye düşürülmez — bozuk yazı basmaktansa durmak yeğdir.)');

            return self::FAILURE;
        }

        $this->font = $font;
        $this->line('  Yazı tipi: '.$font);

        $dizin = $this->option('dizin') ?: base_path('docs/onizleme');
        if (! is_dir($dizin) && ! mkdir($dizin, 0o755, true)) {
            $this->error("Çıktı klasörü açılamadı: {$dizin}");

            return self::FAILURE;
        }

        // Yakın plan bölgesini ağır taramadan ÖNCE çöz. Yanlış yazılmış bir
        // anahtar, 50 saniyelik dünya taramasından ve iki PNG yazıldıktan
        // sonra fark ediliyordu; çıktı klasörü de yarım kalıyordu.
        $bolgeAnahtari = (string) $this->option('bolge');
        $bolge = collect(config('regions', []))->firstWhere('key', $bolgeAnahtari);

        if (! $bolge) {
            $this->error("Bölge bulunamadı: {$bolgeAnahtari}");
            $this->line('  Seçenekler: '.collect(config('regions'))->pluck('key')->implode(', '));

            return self::FAILURE;
        }

        $en = (int) config('game.world.width');
        $boy = (int) config('game.world.height');
        $adim = max(1, (int) $this->option('adim'));
        $olcek = max(1, (int) $this->option('olcek'));

        $this->line(sprintf(
            '  Dünya: %d x %d kare · tohum %d · adım %d · ölçek %d',
            $en, $boy, (int) config('game.world.seed'), $adim, $olcek
        ));

        /* -------- 1. Tüm dünya -------- */

        $izgara = $this->araziIzgarasi($dunya, 0, 0, intdiv($en, $adim), intdiv($boy, $adim), $adim, 'dünya  ');
        $sayim = $this->biyomSayimi($izgara);
        $this->histogramiBas($sayim);

        $harita = $this->haritaCiz($izgara, $olcek);
        $this->kaydet($harita, $dizin.'/dunya.png');

        /* -------- 2. Bölge sınırları + lejant -------- */

        $bolgeli = $this->bolgeliGorsel($harita, $izgara, $olcek, $sayim);
        $this->kaydet($bolgeli, $dizin.'/dunya-bolgeler.png');
        imagedestroy($bolgeli);
        imagedestroy($harita);

        /* -------- 3. Bölge denetimi -------- */

        $uyusmazlik = $this->bolgeleriDenetle($dunya);

        /* -------- 4. Yakın plan -------- */


        $yakin = $this->yakinPlan($dunya, $bolge);
        $this->kaydet($yakin, $dizin.'/dunya-yakin.png');
        imagedestroy($yakin);

        // Uyuşmazlık görsellerin kusuru değil, dünya tasarımının kusurudur:
        // görseller yine de üretilir. --kati ile derleme hattı durdurulabilir.
        return $uyusmazlik > 0 && $this->option('kati') ? self::FAILURE : self::SUCCESS;
    }

    /* ================================================================== */
    /*  Arazi örnekleme */
    /* ================================================================== */

    /**
     * Arazi örneklerini toplar. Tek büyük PHP dizisi yerine paketlenmiş
     * katarlar kullanılır: 1600x1600 örnek böylece ~13 MB tutar, 400 MB değil.
     *
     * Yükseklik 16 bit saklanır — tepe gölgesi iki komşu karenin farkından
     * hesaplandığı için 8 bit basamaklı, çirkin bir kabartma verirdi.
     *
     * @return array{en:int,boy:int,adim:int,x0:int,y0:int,biyom:string,yukseklik:string,nem:string,sicaklik:string}
     */
    private function araziIzgarasi(
        WorldService $dunya,
        int $x0,
        int $y0,
        int $en,
        int $boy,
        int $adim,
        string $baslik
    ): array {
        $sira = array_flip(array_keys(WorldService::BIOMES));
        $biyom = $yukseklik = $nem = $sicaklik = [];

        $cubuk = $this->output->createProgressBar($boy);
        $cubuk->setFormat("  {$baslik} %bar% %percent:3s%% %elapsed:6s%");
        $cubuk->start();

        for ($sy = 0; $sy < $boy; $sy++) {
            $sb = $sy2 = $sn = $ss = '';
            $dy = $y0 + $sy * $adim;

            for ($sx = 0; $sx < $en; $sx++) {
                $kare = $dunya->terrainAt($x0 + $sx * $adim, $dy);
                $sb .= chr($sira[$kare['biome']]);
                $e = (int) round($kare['elevation'] * 65535);
                $sy2 .= chr($e >> 8).chr($e & 255);
                $sn .= chr((int) round($kare['moisture'] * 255));
                $ss .= chr((int) round($kare['temperature'] * 255));
            }

            $biyom[] = $sb;
            $yukseklik[] = $sy2;
            $nem[] = $sn;
            $sicaklik[] = $ss;
            $cubuk->advance();
        }

        $cubuk->finish();
        $this->newLine();

        return [
            'en' => $en, 'boy' => $boy, 'adim' => $adim, 'x0' => $x0, 'y0' => $y0,
            'biyom' => implode('', $biyom),
            'yukseklik' => implode('', $yukseklik),
            'nem' => implode('', $nem),
            'sicaklik' => implode('', $sicaklik),
        ];
    }

    /* ================================================================== */
    /*  Çizim */
    /* ================================================================== */

    /** Örnek ızgarasını renkli, tepe gölgeli bir görsele çevirir. */
    private function haritaCiz(array $iz, int $olcek): GdImage
    {
        $en = $iz['en'];
        $boy = $iz['boy'];
        $im = imagecreatetruecolor($en * $olcek, $boy * $olcek);
        imagealphablending($im, false);
        imagesavealpha($im, false);

        $palet = $this->paletiCoz();
        $b = $iz['biyom'];
        $y = $iz['yukseklik'];
        $n = $iz['nem'];
        $s = $iz['sicaklik'];

        // Kabartma abartması: yükseklik 0..1, mesafe kare cinsinden. Tipik
        // eğim binde birkaç olduğu için üç haneli bir çarpan gerekir.
        $abart = 150.0;

        for ($sy = 0; $sy < $boy; $sy++) {
            $ust = $sy > 0 ? $sy - 1 : 0;
            $alt = $sy < $boy - 1 ? $sy + 1 : $boy - 1;
            $dy = ($alt - $ust) * $iz['adim'];
            $satir = $sy * $en;
            $satirUst = $ust * $en;
            $satirAlt = $alt * $en;

            for ($sx = 0; $sx < $en; $sx++) {
                $i = $satir + $sx;
                $p = $palet[ord($b[$i])];

                $e = (ord($y[$i * 2]) << 8 | ord($y[$i * 2 + 1])) / 65535.0;
                $deger = match ($p['d']) {
                    'yukseklik' => $e,
                    'nem' => ord($n[$i]) / 255.0,
                    default => ord($s[$i]) / 255.0,
                };

                $t = $p['ust'] > $p['alt'] ? ($deger - $p['alt']) / ($p['ust'] - $p['alt']) : 0.5;
                $t = $t < 0 ? 0.0 : ($t > 1 ? 1.0 : $t);

                $r = (int) ($p['a'][0] + ($p['b'][0] - $p['a'][0]) * $t);
                $g = (int) ($p['a'][1] + ($p['b'][1] - $p['a'][1]) * $t);
                $m = (int) ($p['a'][2] + ($p['b'][2] - $p['a'][2]) * $t);

                // Tepe gölgesi: kuzeybatıdan gelen ışık.
                $sol = $sx > 0 ? $sx - 1 : 0;
                $sag = $sx < $en - 1 ? $sx + 1 : $en - 1;
                $dx = ($sag - $sol) * $iz['adim'];

                $es = (ord($y[($satir + $sol) * 2]) << 8 | ord($y[($satir + $sol) * 2 + 1])) / 65535.0;
                $eg = (ord($y[($satir + $sag) * 2]) << 8 | ord($y[($satir + $sag) * 2 + 1])) / 65535.0;
                $eu = (ord($y[($satirUst + $sx) * 2]) << 8 | ord($y[($satirUst + $sx) * 2 + 1])) / 65535.0;
                $ea = (ord($y[($satirAlt + $sx) * 2]) << 8 | ord($y[($satirAlt + $sx) * 2 + 1])) / 65535.0;

                $carpan = $this->isik(
                    $dx > 0 ? ($eg - $es) / $dx : 0.0,
                    $dy > 0 ? ($ea - $eu) / $dy : 0.0,
                    $abart * $p['kab']
                );

                $r = (int) ($r * $carpan);
                $g = (int) ($g * $carpan);
                $m = (int) ($m * $carpan);
                $renk = (($r > 255 ? 255 : $r) << 16) | (($g > 255 ? 255 : $g) << 8) | ($m > 255 ? 255 : $m);

                if ($olcek === 1) {
                    imagesetpixel($im, $sx, $sy, $renk);
                } else {
                    imagefilledrectangle(
                        $im, $sx * $olcek, $sy * $olcek,
                        $sx * $olcek + $olcek - 1, $sy * $olcek + $olcek - 1, $renk
                    );
                }
            }
        }

        return $im;
    }

    /**
     * Yüzey normali ile kuzeybatıdan gelen ışığın iç çarpımı.
     * Düz zeminde 1.0 döner; yamaçta 0.55 (gölge) ile 1.45 (ışık) arası.
     */
    private function isik(float $dzdx, float $dzdy, float $abart): float
    {
        $nx = -$dzdx * $abart;
        $ny = -$dzdy * $abart;
        $uz = sqrt($nx * $nx + $ny * $ny + 1.0);

        // Işık yönü: sol-üst, 45 derece yükseklikte.
        $nokta = (-0.5774 * $nx - 0.5774 * $ny + 0.5774) / $uz;

        $carpan = 1.0 + ($nokta - 0.5774) * 1.05;

        return $carpan < 0.62 ? 0.62 : ($carpan > 1.34 ? 1.34 : $carpan);
    }

    /** @return array<int,array{a:array<int,int>,b:array<int,int>,d:string,alt:float,ust:float,kab:float}> */
    private function paletiCoz(): array
    {
        $cozulmus = [];

        foreach (array_keys(WorldService::BIOMES) as $sira => $ad) {
            if (! isset(self::PALET[$ad])) {
                throw new \RuntimeException(
                    "Palette eksik biyom: '{$ad}'. WorldService::BIOMES'a yeni biyom eklendiyse ".
                    'PreviewWorldCommand::PALET de güncellenmeli.'
                );
            }

            $p = self::PALET[$ad];
            $cozulmus[$sira] = [
                'a' => $this->renkCoz($p['dusuk']),
                'b' => $this->renkCoz($p['yuksek']),
                'd' => $p['degisken'],
                'alt' => (float) $p['alt'],
                'ust' => (float) $p['ust'],
                'kab' => (float) $p['kabartma'],
            ];
        }

        return $cozulmus;
    }

    /* ================================================================== */
    /*  Bölge katmanı + lejant */
    /* ================================================================== */

    /** Haritanın kopyası üzerine bölgeleri ve sağ paneli çizer. */
    private function bolgeliGorsel(GdImage $harita, array $iz, int $olcek, array $sayim): GdImage
    {
        $hEn = imagesx($harita);
        $hBoy = imagesy($harita);
        $this->birim = max(0.5, min(2.0, $hBoy / 1600));
        $panel = (int) round(380 * $this->birim);

        $im = imagecreatetruecolor($hEn + $panel, $hBoy);
        imagealphablending($im, true);
        imagecopy($im, $harita, 0, 0, 0, 0, $hEn, $hBoy);

        $this->bolgeHalkalari($im, $iz, $olcek);
        $this->olcekCubugu($im, $iz, $olcek, $hBoy);
        $this->panel($im, $hEn, $panel, $hBoy, $sayim);

        return $im;
    }

    /** 16 bölgenin dairesi, merkezi ve adı. */
    private function bolgeHalkalari(GdImage $im, array $iz, int $olcek): void
    {
        $en = (int) config('game.world.width');
        $boy = (int) config('game.world.height');
        $kucuk = min($en, $boy);
        $kare = $olcek / $iz['adim'];   // bir dünya karesi kaç piksel
        $u = $this->birim;

        $kara = $this->renkAyir($im, '#0b0e13', 72);
        $beyaz = imagecolorallocate($im, 245, 248, 252);
        $golge = imagecolorallocate($im, 8, 11, 16);

        $kutular = [];
        $adPunto = 17 * $u;
        $altPunto = 12 * $u;
        $satirAra = (int) round(19 * $u);

        foreach (config('regions', []) as $b) {
            $px = (int) round($b['at'][0] * $en * $kare);
            $py = (int) round($b['at'][1] * $boy * $kare);
            $pr = (int) round($b['radius'] * $kucuk * $kare);
            [$r, $g, $m] = $this->renkCoz($b['color']);
            $bolgeRengi = imagecolorallocate($im, $r, $g, $m);
            $solukDolgu = imagecolorallocatealpha($im, $r, $g, $m, 108);

            imagefilledellipse($im, $px, $py, $pr * 2, $pr * 2, $solukDolgu);

            imagesetthickness($im, max(4, (int) round(6 * $kare)));
            imageellipse($im, $px, $py, $pr * 2, $pr * 2, $kara);
            imagesetthickness($im, max(2, (int) round(3 * $kare)));
            imageellipse($im, $px, $py, $pr * 2, $pr * 2, $bolgeRengi);
            imagesetthickness($im, 1);

            // Merkez nişanı
            $nisan = max(7, (int) round(13 * $u));
            imagefilledellipse($im, $px, $py, $nisan, $nisan, $golge);
            imagefilledellipse($im, $px, $py, $nisan - 4, $nisan - 4, $bolgeRengi);

            $ad = $b['name'];
            $altYazi = WorldService::BIOMES[$b['biome']]['name'] ?? $b['biome'];
            $kutuEn = max($this->metinEni($adPunto, $ad), $this->metinEni($altPunto, $altYazi));
            $kutuBoy = (int) round(46 * $u);

            // Etiket önce merkez nişanının dibine konur — ad nereyi
            // gösterdiği belli olsun. Dolu ise sırayla dışarı taşınır;
            // uzağa düşerse kılavuz çizgisi merkeze bağlar.
            $yerler = [
                [$px, $py + (int) round(29 * $u)],
                [$px, $py - (int) round(17 * $u)],
                [$px, $py + $pr + (int) round(28 * $u)],
                [$px, $py - $pr - (int) round(16 * $u)],
                [$px + $pr + (int) round(12 * $u) + intdiv($kutuEn, 2), $py + (int) round(6 * $u)],
                [$px - $pr - (int) round(12 * $u) - intdiv($kutuEn, 2), $py + (int) round(6 * $u)],
                [$px, $py + $pr + (int) round(74 * $u)],
                [$px, $py - $pr - (int) round(62 * $u)],
            ];

            [$ex, $ey] = $this->bosYer($yerler, $kutuEn, $kutuBoy, $kutular, imagesx($im), imagesy($im));
            $kutular[] = [
                $ex - intdiv($kutuEn, 2) - 4, $ey - intdiv($kutuBoy, 2),
                $ex + intdiv($kutuEn, 2) + 4, $ey + intdiv($kutuBoy, 2),
            ];

            // Kılavuz çizgisi: etiket daireden uzağa düştüyse merkeze bağla.
            // Önce çizilir, sonra yazı üstüne basılır — yazının halkası
            // çizgiyi keser, böylece ok ucu gibi görünür.
            if (abs($ex - $px) > 4 || abs($ey - $py) > 4) {
                imagesetthickness($im, max(2, (int) round(3 * $u)));
                imageline($im, $px, $py, $ex, $ey - (int) round(5 * $u), $kara);
                imagesetthickness($im, max(1, (int) round(1.5 * $u)));
                imageline($im, $px, $py, $ex, $ey - (int) round(5 * $u), $bolgeRengi);
                imagesetthickness($im, 1);
            }

            $this->yazi($im, $adPunto, $ex, $ey, $ad, $beyaz, $golge, 'orta');
            $this->yazi($im, $altPunto, $ex, $ey + $satirAra, $altYazi, $bolgeRengi, $golge, 'orta');
        }
    }

    /**
     * Aday noktalar arasından, daha önce yazılmış etiketlerle çakışmayan
     * ve görselin dışına taşmayan ilkini seçer.
     *
     * @param  array<int,array{0:int,1:int}>  $yerler
     * @param  array<int,array{0:int,1:int,2:int,3:int}>  $kutular
     * @return array{0:int,1:int}
     */
    private function bosYer(array $yerler, int $en, int $boy, array $kutular, int $gEn, int $gBoy): array
    {
        foreach ($yerler as [$x, $y]) {
            $sol = $x - intdiv($en, 2);
            $sag = $x + intdiv($en, 2);
            $ust = $y - intdiv($boy, 2);
            $alt = $y + intdiv($boy, 2);

            if ($sol < 6 || $sag > $gEn - 6 || $ust < 6 || $alt > $gBoy - 6) {
                continue;
            }

            $cakisti = false;
            foreach ($kutular as $k) {
                if ($sol < $k[2] && $sag > $k[0] && $ust < $k[3] && $alt > $k[1]) {
                    $cakisti = true;
                    break;
                }
            }

            if (! $cakisti) {
                return [$x, $y];
            }
        }

        return $yerler[0];
    }

    /** Sol alt köşeye 200 karelik mesafe çubuğu. */
    private function olcekCubugu(GdImage $im, array $iz, int $olcek, int $hBoy): void
    {
        $u = $this->birim;
        $kare = $olcek / $iz['adim'];
        $uzunluk = (int) round(200 * $kare);
        $x = (int) round(28 * $u);
        $y = $hBoy - (int) round(34 * $u);

        $golge = imagecolorallocate($im, 8, 11, 16);
        $beyaz = imagecolorallocate($im, 240, 245, 250);
        $tirnak = max(4, (int) round(7 * $u));

        imagesetthickness($im, max(3, (int) round(6 * $u)));
        imageline($im, $x, $y, $x + $uzunluk, $y, $golge);
        imagesetthickness($im, max(1, (int) round(2 * $u)));
        imageline($im, $x, $y, $x + $uzunluk, $y, $beyaz);
        imageline($im, $x, $y - $tirnak, $x, $y + $tirnak, $beyaz);
        imageline($im, $x + $uzunluk, $y - $tirnak, $x + $uzunluk, $y + $tirnak, $beyaz);
        imagesetthickness($im, 1);

        $this->yazi($im, 13 * $u, $x, $y - (int) round(13 * $u), '200 kare', $beyaz, $golge);
    }

    /** Sağdaki bilgi paneli: biyom histogramı + bölge listesi. */
    private function panel(GdImage $im, int $x0, int $en, int $boy, array $sayim): void
    {
        $u = $this->birim;
        $zemin = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_ZEMIN));
        $cizgi = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_CIZGI));
        $yazi = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_YAZI));
        $soluk = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_SOLUK));

        imagefilledrectangle($im, $x0, 0, $x0 + $en, $boy, $zemin);
        imageline($im, $x0, 0, $x0, $boy, $cizgi);

        $sol = $x0 + (int) round(28 * $u);
        $sag = $x0 + $en - (int) round(28 * $u);
        $y = (int) round(58 * $u);

        $this->yazi($im, 22 * $u, $sol, $y, 'Klanlar Vadisi', $yazi);
        $y += (int) round(28 * $u);
        $this->yazi($im, 13 * $u, $sol, $y, 'Dünya Haritası — kuşbakışı', $soluk);
        $y += (int) round(22 * $u);
        $this->yazi($im, 11 * $u, $sol, $y, sprintf(
            '%d x %d kare · tohum %d',
            (int) config('game.world.width'),
            (int) config('game.world.height'),
            (int) config('game.world.seed')
        ), $soluk);

        $y += (int) round(34 * $u);
        imageline($im, $sol, $y, $sag, $y, $cizgi);
        $y += (int) round(30 * $u);

        $toplam = max(1, array_sum($sayim));
        $this->yazi($im, 13 * $u, $sol, $y, 'BİYOMLAR', $soluk);
        $y += (int) round(26 * $u);

        arsort($sayim);
        $kutuEn = (int) round(20 * $u);
        $kutuBoy = (int) round(15 * $u);

        foreach ($sayim as $anahtar => $adet) {
            $p = self::PALET[$anahtar];
            $kutu = imagecolorallocate($im, ...$this->renkCoz($p['yuksek']));
            $kenar = imagecolorallocate($im, ...$this->renkCoz($p['dusuk']));

            imagefilledrectangle($im, $sol, $y - $kutuBoy + 3, $sol + $kutuEn, $y + 3, $kutu);
            imagerectangle($im, $sol, $y - $kutuBoy + 3, $sol + $kutuEn, $y + 3, $kenar);

            $this->yazi($im, 14 * $u, $sol + (int) round(30 * $u), $y, WorldService::BIOMES[$anahtar]['name'], $yazi);
            $this->yazi($im, 14 * $u, $sag, $y, number_format($adet / $toplam * 100, 1).'%', $soluk, null, 'sag');
            $y += (int) round(25 * $u);
        }

        $y += (int) round(14 * $u);
        imageline($im, $sol, $y, $sag, $y, $cizgi);
        $y += (int) round(30 * $u);

        $this->yazi($im, 13 * $u, $sol, $y, 'BÖLGELER', $soluk);
        $y += (int) round(26 * $u);

        foreach (config('regions', []) as $b) {
            $nokta = imagecolorallocate($im, ...$this->renkCoz($b['color']));
            imagefilledellipse($im, $sol + intdiv($kutuEn, 2), $y - (int) round(5 * $u), $kutuEn - 4, $kutuEn - 4, $nokta);

            $adX = $sol + (int) round(30 * $u);
            $this->yazi($im, 14 * $u, $adX, $y, $b['name'], $yazi);

            // Biyom adı yalnızca ad ile çakışmıyorsa yazılır.
            $biyomAdi = WorldService::BIOMES[$b['biome']]['name'] ?? $b['biome'];
            $bos = $sag - $adX - $this->metinEni(14 * $u, $b['name']);
            if ($bos > $this->metinEni(11 * $u, $biyomAdi) + (int) round(12 * $u)) {
                $this->yazi($im, 11 * $u, $sag, $y, $biyomAdi, $soluk, null, 'sag');
            }

            $y += (int) round(24 * $u);
        }

        // Dipnot: görselin nereden geldiği. Kimse "bu renkleri kim uydurdu"
        // diye sormasın.
        $dipnot = [
            'Arazi: WorldService::terrainAt()',
            'Bölgeler: config/regions.php',
            "Unity'de (WorldTerrain.Ornekle)",
            'aynı dünyayı çizer.',
            'php artisan game:onizleme-dunya',
        ];

        $y = $boy - (int) round(28 * $u) - count($dipnot) * (int) round(17 * $u);
        imageline($im, $sol, $y - (int) round(24 * $u), $sag, $y - (int) round(24 * $u), $cizgi);

        foreach ($dipnot as $satir) {
            $this->yazi($im, 10.5 * $u, $sol, $y, $satir, $soluk);
            $y += (int) round(17 * $u);
        }
    }

    /* ================================================================== */
    /*  Yakın plan */
    /* ================================================================== */

    /** Bir bölgenin çevresinden yüksek çözünürlüklü kesit. */
    private function yakinPlan(WorldService $dunya, array $bolge): GdImage
    {
        $en = (int) config('game.world.width');
        $boy = (int) config('game.world.height');
        $kenar = max(40, (int) $this->option('yakin'));
        $olcek = max(1, (int) $this->option('yakin-olcek'));

        $merkezX = (int) round($bolge['at'][0] * $en);
        $merkezY = (int) round($bolge['at'][1] * $boy);
        $x0 = max(0, min($en - $kenar, $merkezX - intdiv($kenar, 2)));
        $y0 = max(0, min($boy - $kenar, $merkezY - intdiv($kenar, 2)));

        $iz = $this->araziIzgarasi($dunya, $x0, $y0, $kenar, $kenar, 1, 'yakın  ');
        $harita = $this->haritaCiz($iz, $olcek);

        $hEn = imagesx($harita);
        $hBoy = imagesy($harita);
        $this->birim = $u = max(0.6, min(1.6, $hEn / 800));
        $ustluk = (int) round(110 * $u);

        // Alt şerit: kesitte görünen biyomlar. Kaç satır tutacağı önceden
        // hesaplanır, yoksa son biyomlar görselin dışında kalır.
        $sayim = $this->biyomSayimi($iz);
        arsort($sayim);
        $satirlar = $this->lejantSatirlari($sayim, $hEn - (int) round(48 * $u), $u);
        $altlik = (int) round(26 * $u) + count($satirlar) * (int) round(24 * $u);

        $im = imagecreatetruecolor($hEn, $hBoy + $ustluk + $altlik);
        imagealphablending($im, true);

        $zemin = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_ZEMIN));
        $cizgi = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_CIZGI));
        $yaziR = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_YAZI));
        $soluk = imagecolorallocate($im, ...$this->renkCoz(self::PANEL_SOLUK));

        imagefilledrectangle($im, 0, 0, $hEn, $hBoy + $ustluk + $altlik, $zemin);
        imagecopy($im, $harita, 0, $ustluk, 0, 0, $hEn, $hBoy);
        imagedestroy($harita);

        $kenarBosluk = (int) round(24 * $u);
        $kullanilir = $hEn - 2 * $kenarBosluk;

        $this->yaziSigdir($im, 23 * $u, $kenarBosluk, (int) round(44 * $u), $kullanilir, $bolge['name'], $yaziR);
        $this->yaziSigdir($im, 12 * $u, $kenarBosluk, (int) round(70 * $u), $kullanilir, sprintf(
            '%s · %d x %d kare · 1 kare = %d piksel',
            WorldService::BIOMES[$bolge['biome']]['name'] ?? $bolge['biome'],
            $kenar, $kenar, $olcek
        ), $soluk);
        $this->yaziSigdir($im, 12 * $u, $kenarBosluk, (int) round(92 * $u), $kullanilir, sprintf(
            'sol üst köşe (%d, %d) · eş yükselti eğrileri %%%s',
            $x0, $y0, number_format(self::EGRI_ARALIGI * 100, 1)
        ), $soluk);

        // Buradan sonrası yalnızca harita alanına çizilir; daire ve ızgara
        // başlık şeridine taşmasın.
        imagesetclip($im, 0, $ustluk, $hEn - 1, $ustluk + $hBoy - 1);

        // Eş yükselti eğrileri: bölge çekirdeği düz göründüğünde bile
        // arazinin gerçek eğimi okunsun.
        $this->esYukseltiler($im, $iz, $olcek, 0, $ustluk);

        // Ölçek ızgarası: 25 karede bir soluk çizgi + dünya koordinatı.
        $izgaraRengi = imagecolorallocatealpha($im, 255, 255, 255, 104);
        $golge = imagecolorallocate($im, 8, 11, 16);

        for ($k = 25; $k < $kenar; $k += 25) {
            $p = $k * $olcek;
            imageline($im, $p, $ustluk, $p, $ustluk + $hBoy, $izgaraRengi);
            imageline($im, 0, $ustluk + $p, $hEn, $ustluk + $p, $izgaraRengi);
            $this->yazi($im, 10 * $u, $p + 4, $ustluk + (int) round(15 * $u), (string) ($x0 + $k), $yaziR, $golge);
            $this->yazi($im, 10 * $u, 4, $ustluk + $p - 4, (string) ($y0 + $k), $yaziR, $golge);
        }

        // Bölgenin sınır dairesi ve merkez nişanı — dünya haritasındakiyle
        // aynı daire, yalnızca yakından.
        $bx = (int) round(($bolge['at'][0] * $en - $x0) * $olcek);
        $by = $ustluk + (int) round(($bolge['at'][1] * $boy - $y0) * $olcek);
        $br = (int) round($bolge['radius'] * min($en, $boy) * $olcek);
        [$rr, $gg, $mm] = $this->renkCoz($bolge['color']);
        $bolgeRengi = imagecolorallocate($im, $rr, $gg, $mm);
        $kara = imagecolorallocatealpha($im, 11, 14, 19, 72);

        imagesetthickness($im, max(4, (int) round(6 * $u)));
        imageellipse($im, $bx, $by, $br * 2, $br * 2, $kara);
        imagesetthickness($im, max(2, (int) round(3 * $u)));
        imageellipse($im, $bx, $by, $br * 2, $br * 2, $bolgeRengi);
        imagesetthickness($im, 1);

        $nisan = max(9, (int) round(15 * $u));
        imagefilledellipse($im, $bx, $by, $nisan, $nisan, $golge);
        imagefilledellipse($im, $bx, $by, $nisan - 5, $nisan - 5, $bolgeRengi);
        $this->yazi($im, 12 * $u, $bx + $nisan, $by - (int) round(8 * $u), 'merkez', $bolgeRengi, $golge);

        imagesetclip($im, 0, 0, $hEn - 1, imagesy($im) - 1);
        imagerectangle($im, 0, $ustluk, $hEn - 1, $ustluk + $hBoy - 1, $cizgi);

        $y = $ustluk + $hBoy + (int) round(32 * $u);
        $kutuEn = (int) round(15 * $u);

        foreach ($satirlar as $satir) {
            $x = (int) round(24 * $u);

            foreach ($satir as [$anahtar, $etiket, $genislik]) {
                imagefilledrectangle(
                    $im, $x, $y - $kutuEn + 2, $x + $kutuEn, $y + 2,
                    imagecolorallocate($im, ...$this->renkCoz(self::PALET[$anahtar]['yuksek']))
                );
                $this->yazi($im, 12 * $u, $x + (int) round(23 * $u), $y, $etiket, $soluk);
                $x += $genislik + (int) round(44 * $u);
            }

            $y += (int) round(24 * $u);
        }

        return $im;
    }

    /**
     * Alt şerit lejantını satırlara böler: bir satıra sığmayan biyom bir
     * alt satıra iner, hiçbiri kırpılmaz.
     *
     * @param  array<string,int>  $sayim
     * @return array<int,array<int,array{0:string,1:string,2:int}>>
     */
    private function lejantSatirlari(array $sayim, int $enSiniri, float $u): array
    {
        $toplam = max(1, array_sum($sayim));
        $satirlar = [[]];
        $x = 0;

        foreach ($sayim as $anahtar => $adet) {
            $oran = $adet / $toplam * 100;
            if ($oran < 0.5) {
                continue;
            }

            $etiket = WorldService::BIOMES[$anahtar]['name'].' %'.number_format($oran, 0);
            $genislik = $this->metinEni(12 * $u, $etiket) + (int) round(44 * $u);

            if ($x > 0 && $x + $genislik > $enSiniri) {
                $satirlar[] = [];
                $x = 0;
            }

            $satirlar[count($satirlar) - 1][] = [$anahtar, $etiket, $genislik - (int) round(44 * $u)];
            $x += $genislik;
        }

        return array_values(array_filter($satirlar));
    }

    /**
     * Eş yükselti eğrileri. İki komşu kare farklı yükselti kuşağına
     * düşüyorsa aradaki piksel çizgisi koyulaştırılır — böylece aynı
     * biyomun içindeki eğim de görünür olur.
     */
    private function esYukseltiler(GdImage $im, array $iz, int $olcek, int $ox, int $oy): void
    {
        $en = $iz['en'];
        $boy = $iz['boy'];
        $y = $iz['yukseklik'];
        $cizgi = imagecolorallocatealpha($im, 12, 16, 22, 88);

        // Su üstünde eş yükselti eğrisi anlamsız: deniz ve nehir karelerini
        // atlıyoruz, yoksa gölün ortasında kıvrım kıvrım çizgiler çıkıyor.
        $b = $iz['biyom'];
        $suKodlari = [];
        foreach (array_keys(WorldService::BIOMES) as $k => $ad) {
            if ($ad === 'deniz' || $ad === 'nehir') {
                $suKodlari[$k] = true;
            }
        }
        $su = static fn (int $i): bool => isset($suKodlari[ord($b[$i])]);

        $kusak = static function (int $i) use ($y): int {
            $e = (ord($y[$i * 2]) << 8 | ord($y[$i * 2 + 1])) / 65535.0;

            return (int) floor($e / self::EGRI_ARALIGI);
        };

        for ($sy = 0; $sy < $boy; $sy++) {
            for ($sx = 0; $sx < $en; $sx++) {
                $i = $sy * $en + $sx;

                if ($su($i)) {
                    continue;
                }

                $k = $kusak($i);
                $px = $ox + $sx * $olcek;
                $py = $oy + $sy * $olcek;

                if ($sx + 1 < $en && ! $su($i + 1) && $kusak($i + 1) !== $k) {
                    imageline($im, $px + $olcek - 1, $py, $px + $olcek - 1, $py + $olcek - 1, $cizgi);
                }

                if ($sy + 1 < $boy && ! $su($i + $en) && $kusak($i + $en) !== $k) {
                    imageline($im, $px, $py + $olcek - 1, $px + $olcek - 1, $py + $olcek - 1, $cizgi);
                }
            }
        }
    }

    /* ================================================================== */
    /*  Ölçüm ve denetim */
    /* ================================================================== */

    /** @return array<string,int> */
    private function biyomSayimi(array $iz): array
    {
        $anahtarlar = array_keys(WorldService::BIOMES);
        $sayim = array_fill_keys($anahtarlar, 0);
        $ham = count_chars($iz['biyom'], 1);

        foreach ($ham as $kod => $adet) {
            if (isset($anahtarlar[$kod])) {
                $sayim[$anahtarlar[$kod]] = $adet;
            }
        }

        return $sayim;
    }

    /** @param  array<string,int>  $sayim */
    private function histogramiBas(array $sayim): void
    {
        $toplam = max(1, array_sum($sayim));
        $satirlar = [];

        arsort($sayim);
        foreach ($sayim as $anahtar => $adet) {
            $oran = $adet / $toplam * 100;
            $satirlar[] = [
                $anahtar,
                WorldService::BIOMES[$anahtar]['name'],
                number_format($adet),
                number_format($oran, 2).'%',
                str_repeat('#', (int) round($oran / 2)),
            ];
        }

        $this->newLine();
        $this->line('  Biyom dağılımı');
        $this->table(['anahtar', 'ad', 'kare', 'oran', ''], $satirlar);
        // Kara = Kök Deniz dışındaki her şey; nehir karanın içinden akar,
        // okyanus sayılmaz. Hedef: dünyanın dörtte üçü kara olsun.
        $this->line(sprintf(
            '  Kara %s%%  ·  Kök Deniz %s%%  ·  nehir (kara içi) %s%%',
            number_format(100 - $sayim['deniz'] / $toplam * 100, 2),
            number_format($sayim['deniz'] / $toplam * 100, 2),
            number_format($sayim['nehir'] / $toplam * 100, 2)
        ));
        $this->newLine();
    }

    /**
     * Her bölge gerçekten ilan ettiği biyomda mı?
     *
     * İki ölçü alınır: merkez karesi ve çekirdek (yarıçapın üçte biri)
     * içindeki baskın biyom. Nehir bir örtüdür — çayırın üstünden de,
     * bataklığın üstünden de geçer — bu yüzden baskınlık hesabından
     * çıkarılır, ama payı ayrı sütunda gösterilir. İkisinden biri ilan
     * edilen biyomu tutturuyorsa bölge yerindedir.
     *
     * @return int uyuşmayan bölge sayısı
     */
    private function bolgeleriDenetle(WorldService $dunya): int
    {
        $en = (int) config('game.world.width');
        $boy = (int) config('game.world.height');
        $kucuk = min($en, $boy);
        $satirlar = [];
        $uyusmaz = 0;
        $uyusmayanlar = [];

        foreach (config('regions', []) as $b) {
            $cx = (int) round($b['at'][0] * $en);
            $cy = (int) round($b['at'][1] * $boy);
            $cekirdek = $b['radius'] * $kucuk / 3.0;

            $merkez = $dunya->terrainAt($cx, $cy)['biome'];

            $sayim = [];
            $adim = max(1, (int) round($cekirdek / 9));
            for ($y = -(int) $cekirdek; $y <= (int) $cekirdek; $y += $adim) {
                for ($x = -(int) $cekirdek; $x <= (int) $cekirdek; $x += $adim) {
                    if ($x * $x + $y * $y > $cekirdek ** 2) {
                        continue;
                    }
                    $k = $dunya->terrainAt(
                        max(0, min($en - 1, $cx + $x)),
                        max(0, min($boy - 1, $cy + $y))
                    )['biome'];
                    $sayim[$k] = ($sayim[$k] ?? 0) + 1;
                }
            }

            $toplam = max(1, array_sum($sayim));
            $nehirPayi = ($sayim['nehir'] ?? 0) / $toplam * 100;

            $karada = $sayim;
            unset($karada['nehir']);
            arsort($karada);

            $baskin = $karada === [] ? 'nehir' : (string) array_key_first($karada);
            $baskinPayi = ($karada[$baskin] ?? 0) / max(1, array_sum($karada)) * 100;

            $beklenen = $b['biome'];

            // İlan edilen biyom, bölge çekirdeğinde BASKIN olan biyom olmalı.
            // Eski ölçüt "merkez karesi VEYA baskın" idi; tek bir merkez
            // karesi, çekirdeğin %78'i başka biyom olan bölgeyi "uyuyor"
            // gösteriyordu. Sabit bir yüzde eşiği de koymuyoruz: kıyı
            // bölgelerinde hiçbir biyom %50'yi geçmeyebilir.
            $beklenenPayi = ($karada[$beklenen] ?? 0) / max(1, array_sum($karada)) * 100;
            $tamam = $baskin === $beklenen;

            if (! $tamam) {
                $uyusmaz++;
                $uyusmayanlar[] = sprintf(
                    '%s (%s): ilan "%s" (çekirdekte %%%.0f), merkez "%s", baskın "%s"',
                    $b['name'], $b['key'], $beklenen, $beklenenPayi, $merkez, $baskin
                );
            }

            $satirlar[] = [
                $b['key'],
                $b['name'],
                $beklenen,
                $merkez,
                $baskin.' %'.number_format($baskinPayi, 0),
                '%'.number_format($nehirPayi, 0),
                $tamam ? 'uyuyor' : 'UYUŞMUYOR',
            ];
        }

        $this->line('  Bölge arazi denetimi — config/regions.php `biome` ile gerçek arazi');
        $this->table(
            ['anahtar', 'ad', 'ilan', 'merkez karesi', 'çekirdekte baskın', 'nehir', 'sonuç'],
            $satirlar
        );

        if ($uyusmaz > 0) {
            $this->warn("  {$uyusmaz} bölge ilan ettiği biyomda değil:");
            foreach ($uyusmayanlar as $satir) {
                $this->line('    - '.$satir);
            }
            $this->line('  Düzeltme yeri: config/regions.php -> ilgili bölgenin `terrain` alanı.');
        } else {
            $sayi = count($satirlar);
            $this->info("  {$sayi} bölgenin {$sayi} tanesi de ilan ettiği biyomda.");
        }

        $this->newLine();

        return $uyusmaz;
    }

    /* ================================================================== */
    /*  Yardımcılar */
    /* ================================================================== */

    /** @return array<int,int> [r, g, b] */
    private function renkCoz(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function renkAyir(GdImage $im, string $hex, int $saydam = 0): int
    {
        [$r, $g, $b] = $this->renkCoz($hex);

        return $saydam > 0
            ? imagecolorallocatealpha($im, $r, $g, $b, $saydam)
            : imagecolorallocate($im, $r, $g, $b);
    }

    private function metinEni(float $boyut, string $metin): int
    {
        $kutu = imagettfbbox($boyut, 0, $this->font, $metin);

        return (int) ($kutu[2] - $kutu[0]);
    }

    /** Hizalı, isteğe bağlı gölgeli (okunurluk halkası) metin. */
    private function yazi(
        GdImage $im,
        float $boyut,
        int $x,
        int $y,
        string $metin,
        int $renk,
        ?int $golge = null,
        string $hiza = 'sol'
    ): void {
        if ($hiza !== 'sol') {
            $genislik = $this->metinEni($boyut, $metin);
            $x -= $hiza === 'orta' ? intdiv($genislik, 2) : $genislik;
        }

        if ($golge !== null) {
            foreach ([[-2, 0], [2, 0], [0, -2], [0, 2], [-1, -1], [1, 1], [-1, 1], [1, -1]] as [$dx, $dy]) {
                imagettftext($im, $boyut, 0, $x + $dx, $y + $dy, $golge, $this->font, $metin);
            }
        }

        imagettftext($im, $boyut, 0, $x, $y, $renk, $this->font, $metin);
    }

    /** Verilen genişliğe sığana dek puntoyu küçülterek yazar. */
    private function yaziSigdir(
        GdImage $im,
        float $boyut,
        int $x,
        int $y,
        int $enSiniri,
        string $metin,
        int $renk
    ): void {
        while ($boyut > 6.0 && $this->metinEni($boyut, $metin) > $enSiniri) {
            $boyut -= 0.5;
        }

        $this->yazi($im, $boyut, $x, $y, $metin, $renk);
    }

    private function kaydet(GdImage $im, string $yol): void
    {
        imagepng($im, $yol, 6);
        $this->line(sprintf(
            '  yazıldı  %s  (%d x %d, %s KB)',
            str_replace(base_path().'/', '', $yol),
            imagesx($im), imagesy($im),
            number_format(filesize($yol) / 1024)
        ));
    }

    /* ---------------- Yazı tipi ---------------- */

    /** @return array<int,string> */
    private function fontAdaylari(): array
    {
        $adaylar = array_filter([$this->option('font')]);

        $adaylar = array_merge($adaylar, [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/truetype/crosextra/Carlito-Bold.ttf',
            'C:/Windows/Fonts/segoeuib.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ]);

        // Sistemde fontconfig varsa onun listesini de ekle.
        $cikti = @shell_exec('fc-list --format "%{file}\n" 2>/dev/null');
        if (is_string($cikti)) {
            foreach (preg_split('/\R/', $cikti) ?: [] as $satir) {
                if (preg_match('/\.(ttf|otf)$/i', trim($satir))) {
                    $adaylar[] = trim($satir);
                }
            }
        }

        return array_values(array_unique($adaylar));
    }

    /**
     * Türkçe çizebilen ilk yazı tipini bulur.
     *
     * GD'nin yerleşik fontu (imagestring) Latin-1 ötesini basamaz; Ğ/İ/ş
     * yerine kutu çıkar. Bu yüzden TTF şart. "Dosya var" da yetmez: fontun
     * gerçekten o glifleri taşıdığını, Türkçe harfi ASCII karşılığıyla
     * karşılaştırarak sınıyoruz — taşımıyorsa ikisi aynı kutu olarak çizilir.
     */
    private function fontBul(): ?string
    {
        foreach ($this->fontAdaylari() as $yol) {
            if (is_file($yol) && $this->turkceCizebiliyor($yol)) {
                return $yol;
            }
        }

        return null;
    }

    private function turkceCizebiliyor(string $yol): bool
    {
        foreach ([['Ğ', 'G'], ['ş', 's'], ['ı', 'i'], ['Ö', 'O'], ['Ç', 'C'], ['Ü', 'U']] as [$turkce, $ascii]) {
            $a = $this->glifIzi($yol, $turkce);
            $b = $this->glifIzi($yol, $ascii);

            if ($a === null || $b === null || $a === $b) {
                return false;
            }
        }

        return true;
    }

    /** Tek bir harfi küçük bir tuvale basıp siyah-beyaz parmak izini döndürür. */
    private function glifIzi(string $yol, string $harf): ?string
    {
        $im = @imagecreatetruecolor(48, 48);
        if ($im === false) {
            return null;
        }

        imagefill($im, 0, 0, imagecolorallocate($im, 0, 0, 0));
        $sonuc = @imagettftext($im, 28, 0, 6, 38, imagecolorallocate($im, 255, 255, 255), $yol, $harf);

        if ($sonuc === false) {
            imagedestroy($im);

            return null;
        }

        $iz = '';
        $murekkep = 0;
        for ($y = 0; $y < 48; $y += 2) {
            for ($x = 0; $x < 48; $x += 2) {
                $dolu = (imagecolorat($im, $x, $y) & 0xFF) > 96 ? '1' : '0';
                $murekkep += (int) $dolu;
                $iz .= $dolu;
            }
        }

        imagedestroy($im);

        return $murekkep > 0 ? $iz : null;
    }
}
