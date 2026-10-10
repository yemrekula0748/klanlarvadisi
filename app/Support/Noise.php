<?php

namespace App\Support;

/**
 * Deterministik gürültü (value noise + fBm).
 *
 * ÖNEMLİ: Bu sınıfın birebir aynısı Unity istemcisinde
 * `unity-client/Assets/Scripts/World/Noise.cs` içinde bulunur. Aynı tohum
 * aynı araziyi üretir; böylece 1600x1600 dünya haritasının tek bir karesi
 * dahi veritabanında tutulmaz — sunucu ve istemci aynı sonucu hesaplar.
 */
final class Noise
{
    public function __construct(private readonly int $seed) {}

    /** 2B tamsayı karma — [0,1) aralığında deterministik değer. */
    public function hash(int $x, int $y, int $salt = 0): float
    {
        $h = $this->seed ^ ($x * 374761393) ^ ($y * 668265263) ^ ($salt * 2147483647);
        $h &= 0x7FFFFFFF;
        $h = ($h ^ ($h >> 13)) * 1274126177;
        $h &= 0x7FFFFFFF;
        $h ^= $h >> 16;

        return ($h & 0x7FFFFFFF) / 2147483647.0;
    }

    private static function smooth(float $t): float
    {
        return $t * $t * (3.0 - 2.0 * $t);
    }

    /** Bilinear interpolasyonlu değer gürültüsü. */
    public function value(float $x, float $y, int $salt = 0): float
    {
        $x0 = (int) floor($x);
        $y0 = (int) floor($y);
        $fx = self::smooth($x - $x0);
        $fy = self::smooth($y - $y0);

        $v00 = $this->hash($x0, $y0, $salt);
        $v10 = $this->hash($x0 + 1, $y0, $salt);
        $v01 = $this->hash($x0, $y0 + 1, $salt);
        $v11 = $this->hash($x0 + 1, $y0 + 1, $salt);

        $top = $v00 + ($v10 - $v00) * $fx;
        $bottom = $v01 + ($v11 - $v01) * $fx;

        return $top + ($bottom - $top) * $fy;
    }

    /** Katmanlı (fractal Brownian motion) gürültü — [0,1]. */
    public function fbm(float $x, float $y, int $octaves = 5, float $lacunarity = 2.0, float $gain = 0.5, int $salt = 0): float
    {
        $sum = 0.0;
        $amp = 1.0;
        $norm = 0.0;
        $freq = 1.0;

        for ($i = 0; $i < $octaves; $i++) {
            $sum += $amp * $this->value($x * $freq, $y * $freq, $salt + $i * 101);
            $norm += $amp;
            $amp *= $gain;
            $freq *= $lacunarity;
        }

        return $norm > 0 ? $sum / $norm : 0.0;
    }

    /** Sırt/damar deseni (nehir ve dağ sıraları için) — [0,1]. */
    public function ridge(float $x, float $y, int $octaves = 4, int $salt = 7): float
    {
        $v = $this->fbm($x, $y, $octaves, 2.0, 0.5, $salt);

        return 1.0 - abs($v * 2.0 - 1.0);
    }
}
