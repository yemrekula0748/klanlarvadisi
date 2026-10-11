<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Dünya 1600x1600 kare; arazi ağ üzerinden gönderilmez, iki tarafta da
 * aynı gürültüden hesaplanır. Bu yüzden WorldService.php ile
 * WorldTerrain.cs birebir aynı matematiği kullanmak zorundadır. Ayrışırlarsa
 * oyuncu obasını Unity'de başka bir arazinin üstünde görür ve sorun
 * sessizce, yalnızca görsel olarak ortaya çıkar.
 *
 * C# burada çalıştırılamadığı için eşitliği metin düzeyinde denetliyoruz:
 * iki dosyadaki eşik ve katsayılar aynı mı?
 */
class TerrainParityTest extends TestCase
{
    private const PHP_YOL  = 'app/Services/WorldService.php';
    private const CS_YOL   = 'unity-client/Assets/Scripts/World/WorldTerrain.cs';

    /** Biyom sınıflandırmasının eşikleri — ikisinde de aynı sırada geçmeli. */
    public function test_biyom_esikleri_ayni(): void
    {
        $php = $this->kesit(self::PHP_YOL, 'private function classify', "\n    }");
        $cs  = $this->kesit(self::CS_YOL, 'Biyom Siniflandir', "\n        }");

        $this->assertSame(
            $this->sayilar($php),
            $this->sayilar($cs),
            "Biyom eşikleri ayrışmış.\nPHP: ".self::PHP_YOL."\nC# : ".self::CS_YOL
        );
    }

    /**
     * Bölge şekillendiricisinin ağırlık formülü.
     *
     * Kaynak metni karşılaştırmak yerine formülün taşıdığı sabitleri
     * karşılaştırıyoruz: biri değişip diğeri kalırsa Unity ile sunucu
     * farklı dünya çizer. (Dizgi eşlemesi, formül her elden geçtiğinde
     * testi de güncellemeyi gerektiriyordu.)
     */
    public function test_bolge_sekillendirici_sabitleri_ayni(): void
    {
        $php = $this->sekillendiriciBlogu(self::PHP_YOL, '$d = sqrt(', '$elevation +=');
        $cs  = $this->sekillendiriciBlogu(self::CS_YOL, 'var dist = Mathf.Sqrt', 'elevation +=');

        // Sınır bozma gürültüsü ve çekirdek platosunun sabitleri. Blokların
        // tamamındaki sayıları karşılaştırmak kırılgan olurdu (PHP `** 2`
        // yazarken C# `dx * dx` yazıyor), bu yüzden anlamı olan sabitleri
        // adlarıyla arıyoruz.
        $sabitler = [
            '0.78' => 'sınır bozma taban katsayısı',
            '0.44' => 'sınır bozma genliği',
            '90'   => 'sınır gürültüsünün ölçeği',
            '97'   => 'sınır gürültüsünün tohumu',
            '1.7'  => 'çekirdek platosu çarpanı',
        ];

        foreach ($sabitler as $sabit => $ne) {
            $this->assertStringContainsString($sabit, $php, "PHP tarafında {$ne} ({$sabit}) yok.");
            $this->assertStringContainsString($sabit, $cs, "C# tarafında {$ne} ({$sabit}) yok — PHP ile ayrışmış.");
        }
    }

    /** Nehir eşiği ve yükseklik aralığı. */
    public function test_nehir_esikleri_ayni(): void
    {
        $php = file_get_contents(base_path(self::PHP_YOL));
        $cs  = file_get_contents(base_path(self::CS_YOL));

        foreach (['0.9715', '280', '53'] as $sabit) {
            $this->assertStringContainsString($sabit, $php, "PHP'de nehir sabiti {$sabit} yok.");
            $this->assertStringContainsString($sabit, $cs, "C#'ta nehir sabiti {$sabit} yok.");
        }
    }

    /** Bölge tanımları config'den Unity'ye doğru aktarılmış mı? */
    public function test_bolge_tanimlari_unityye_aktarilmis(): void
    {
        $cs = file_get_contents(base_path('unity-client/Assets/Scripts/World/BolgeTanimlari.cs'));

        foreach (config('regions') as $bolge) {
            $this->assertStringContainsString(
                '"'.$bolge['key'].'"',
                $cs,
                "{$bolge['key']} bölgesi Unity'ye aktarılmamış — `php artisan game:export-unity` çalıştırın."
            );

            // Şekillendirici gücü de aktarılmalı; değişirse dünya ayrışır.
            $this->assertStringContainsString(
                $this->csSayi($bolge['terrain']['strength']),
                $cs,
                "{$bolge['key']} için şekillendirici gücü Unity'de güncel değil."
            );
        }
    }

    /* ------------------------------------------------------------------ */

    /** İki imleç arasındaki kaynak parçasını döndürür. */
    private function sekillendiriciBlogu(string $yol, string $bas, string $son): string
    {
        $metin = file_get_contents(base_path($yol));
        $i = strpos($metin, $bas);
        $this->assertNotFalse($i, "{$yol} içinde '{$bas}' bulunamadı.");
        $j = strpos($metin, $son, $i);
        $this->assertNotFalse($j, "{$yol} içinde '{$son}' bulunamadı.");

        return substr($metin, $i, $j - $i);
    }

    private function kesit(string $yol, string $baslangic, string $bitis): string
    {
        $metin = file_get_contents(base_path($yol));
        $i = strpos($metin, $baslangic);
        $this->assertNotFalse($i, "{$yol} içinde '{$baslangic}' bulunamadı.");

        $j = strpos($metin, $bitis, $i);
        $this->assertNotFalse($j, "{$yol} içinde gövde sonu bulunamadı.");

        return substr($metin, $i, $j - $i);
    }

    /**
     * Yalnızca karşılaştırma içeren satırlardaki eşikleri toplar.
     *
     * Gövdenin tamamını taramak kırılgan: C# ifade gövdeli metotlar
     * kullandığı için kesit sonraki metoda taşabiliyor ve alakasız
     * sayıları içeri alıyor. Eşikler her zaman bir `<` ya da `>`
     * karşılaştırmasının yanındadır.
     */
    private function sayilar(string $metin): array
    {
        $esikler = [];

        foreach (explode("\n", str_replace('f', '', $metin)) as $satir) {
            if (! str_contains($satir, '<') && ! str_contains($satir, '>')) {
                continue;
            }

            preg_match_all('/\d+\.\d+/', $satir, $m);
            foreach ($m[0] as $n) {
                $esikler[] = (float) $n;
            }
        }

        return $esikler;
    }

    private function csSayi(float $deger): string
    {
        return rtrim(rtrim(sprintf('%.6F', $deger), '0'), '.').'f';
    }
}
