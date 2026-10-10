<?php

namespace App\Services;

use App\Models\Building;
use App\Models\BuildingType;
use App\Models\Village;

/**
 * Belirli bir Otağ seviyesi için düzenli, oynanabilir ve bakımlı görünen
 * bir oba düzeni üretir.
 *
 * Yerleşim sırası önemlidir — surlar ilk kurulur, yoksa yerini başka
 * binalar kapar:
 *   1. Otağ tam merkezde.
 *   2. Surlar: merkez çevresinde kare kale, iki kapı bırakılır.
 *   3. Savunma kuleleri surların İÇİNDE, menzilleri çakışacak biçimde.
 *   4. Depolar ve ordu yapıları çekirdekte (en değerliler en içte).
 *   5. Kaynak yapıları surların DIŞINDA — yem ve tampon görevi görür.
 *   6. Tuzaklar kapı önlerine ve dış çembere gömülür.
 *
 * Hem demo/NPC obaları hem de oyuncuya "hazır düzen" sunmak için kullanılır.
 */
class LayoutGenerator
{
    private int $size;
    private int $margin;
    private int $center;
    private int $wallHalf = 9;

    /** @var array<int,array<int,bool>> */
    private array $occupied = [];

    public function __construct()
    {
        $this->size = (int) config('game.grid.size');
        $this->margin = (int) config('game.grid.margin');
        $this->center = intdiv($this->size, 2);
    }

    /**
     * Obayı sıfırlayıp verilen Otağ seviyesine uygun düzeni kurar.
     *
     * @return array{buildings:int,walls:int,defenses:int}
     */
    public function build(Village $village, int $otagLevel): array
    {
        $otagLevel = max(1, min(15, $otagLevel));
        $village->buildings()->delete();
        $this->occupied = [];

        $types = BuildingType::with('levels')->get()->keyBy('key');
        $stats = ['buildings' => 0, 'walls' => 0, 'defenses' => 0];

        // 1) Otağ merkezde
        $otag = $types['otag'];
        $ox = $this->center - intdiv($otag->grid_w, 2);
        $oy = $this->center - intdiv($otag->grid_h, 2);
        if ($this->put($village, $otag, $ox, $oy, $otagLevel)) {
            $stats['buildings']++;
        }

        // 2) Surlar — kale boyutu hem sur bütçesine hem Otağ seviyesine bağlı.
        //    Düşük seviyede bütçe çemberi kapatmaya yetmez: oba "sızdırır",
        //    bu da erken oyunun saldırana açık kalmasını sağlar. Yüksek
        //    seviyede artan bütçe iç bölmelere harcanır.
        $wall = $types['surlar'] ?? null;
        $budget = $wall ? $wall->allowedCount($otagLevel) : 0;
        $this->wallHalf = max(6, min(11, min(
            (int) round($budget / 7),
            6 + intdiv($otagLevel, 2)
        )));
        $stats['walls'] = $wall && $budget > 0
            ? $this->placeWalls($village, $wall, $budget, $otagLevel)
            : 0;
        $stats['buildings'] += $stats['walls'];

        // 3) Savunma kuleleri — surların içinde
        $defenses = $this->expand($types, [
            'okcu_kulesi', 'ates_kulesi', 'yildirim_kulesi', 'gok_okcusu',
            'mancinik', 'tilsim_tasi', 'kartal_gozu', 'kurt_ini',
        ], $otagLevel);
        $stats['defenses'] = $this->placeRing($village, $defenses, $this->wallHalf - 2.5, $otagLevel, inside: true);
        $stats['buildings'] += $stats['defenses'];

        // 4) Çekirdek: en değerli yapılar surların içinde
        //    (yağmalanacak depolar, Boy Otağı, Kurgan, Umay Tapınağı)
        $core = $this->expand($types, [
            'hazine', 'ambar', 'demir_deposu', 'kut_tasi',
            'boy_otagi', 'kurgan', 'umay_tapinagi',
        ], $otagLevel);
        $stats['buildings'] += $this->placeRing($village, $core, $this->wallHalf - 5.5, $otagLevel, inside: true);

        // 5) Üretim ve kaynak yapıları surların dışında — yem ve tampon.
        //    Gerçek düzenlerde de kışla, ordugâh ve madenler dışarıda kalır.
        $outer = $this->expand($types, [
            'kisla', 'kara_kisla', 'ordugah', 'demirci', 'buyu_atolyesi',
            'gozcu_kulesi', 'kam_cadiri',
            'altin_madeni', 'tarla', 'demir_ocagi',
        ], $otagLevel);
        $stats['buildings'] += $this->placeOutside($village, $outer, $otagLevel);

        // 6) Tuzaklar
        $traps = $this->expand($types, [
            'kapan', 'sivri_kazik', 'hava_kapani', 'devrilen_tas', 'albiz_tuzagi',
        ], $otagLevel);
        $stats['buildings'] += $this->placeTraps($village, $traps, $otagLevel);

        $village->update(['otag_level' => $otagLevel]);

        return $stats;
    }

    /**
     * Bina anahtarlarını, bu Otağ seviyesinde izin verilen adede göre
     * tekrarlayan bir listeye açar.
     *
     * @return array<int,BuildingType>
     */
    private function expand(iterable $types, array $keys, int $otagLevel): array
    {
        $out = [];
        foreach ($keys as $key) {
            $type = $types[$key] ?? null;
            if (! $type) {
                continue;
            }
            for ($i = 0, $n = $type->allowedCount($otagLevel); $i < $n; $i++) {
                $out[] = $type;
            }
        }

        return $out;
    }

    /** Merkez çevresine kare kale örer; üst ve alt kenarın ortasında kapı bırakır. */
    private function placeWalls(Village $village, BuildingType $wall, int $budget, int $otagLevel): int
    {
        $level = $this->bestLevelFor($wall, $otagLevel);
        $min = $this->center - $this->wallHalf;
        $max = $this->center + $this->wallHalf;
        $gates = [$this->center, $this->center + 1];
        $placed = 0;

        // Kenarları sırayla ör: üst, sağ, alt, sol (bütçe yetmezse boşluk kalır)
        $ring = [];
        for ($x = $min; $x <= $max; $x++) {
            $ring[] = [$x, $min];
        }
        for ($y = $min + 1; $y <= $max; $y++) {
            $ring[] = [$max, $y];
        }
        for ($x = $max - 1; $x >= $min; $x--) {
            $ring[] = [$x, $max];
        }
        for ($y = $max - 1; $y > $min; $y--) {
            $ring[] = [$min, $y];
        }

        foreach ($ring as [$x, $y]) {
            if ($placed >= $budget) {
                break;
            }
            if (($y === $min || $y === $max) && in_array($x, $gates, true)) {
                continue; // kapı
            }
            if ($this->put($village, $wall, $x, $y, $level)) {
                $placed++;
            }
        }

        // Artan bütçe iç bölmelere harcanır: saldırgan dış çemberi kırsa bile
        // çekirdeğe ulaşmak için ikinci, hatta üçüncü duvarı aşmak zorunda.
        $placed += $this->placeCompartments($village, $wall, $budget - $placed, $level);

        return $placed;
    }

    /**
     * İç bölmeler: çekirdeğin çevresine daha küçük çemberler ve ara duvarlar.
     */
    private function placeCompartments(Village $village, BuildingType $wall, int $budget, int $level): int
    {
        if ($budget < 12) {
            return 0;
        }

        $placed = 0;
        $c = $this->center;

        // Tek bir iç çember: çekirdeği korur ama obayı aşırı güçlendirmez.
        // (Kademeli çemberler denge ölçümünde saldırıyı %50'nin altına
        // düşürüyordu — bkz. `php artisan game:balance`.)
        for ($half = $this->wallHalf - 4; $half >= $this->wallHalf - 4 && $placed < $budget; $half -= 4) {
            $min = $c - $half;
            $max = $c + $half;

            $ring = [];
            for ($x = $min; $x <= $max; $x++) {
                $ring[] = [$x, $min];
                $ring[] = [$x, $max];
            }
            for ($y = $min + 1; $y < $max; $y++) {
                $ring[] = [$min, $y];
                $ring[] = [$max, $y];
            }

            foreach ($ring as [$x, $y]) {
                if ($placed >= $budget) {
                    break;
                }
                // İç çemberde iki kapı (kuzey ve güney)
                if (($y === $min || $y === $max) && ($x === $c || $x === $c + 1)) {
                    continue;
                }
                if ($this->put($village, $wall, $x, $y, $level)) {
                    $placed++;
                }
            }
        }

        return $placed;
    }

    /** Binaları merkez çevresinde halka hâlinde, sur içinde/dışında dizer. */
    private function placeRing(Village $village, array $list, float $radius, int $otagLevel, bool $inside): int
    {
        if (! $list) {
            return 0;
        }

        $placed = 0;
        $n = count($list);
        $radius = max(2.0, $radius);

        foreach ($list as $i => $type) {
            // Eşit açı + hafif yarıçap salınımı: halkalar homojen dolar.
            $angle = ($i / $n) * M_PI * 2;
            $r = $radius + (($i % 3) - 1) * 1.1;

            $x = (int) round($this->center + cos($angle) * $r - $type->grid_w / 2);
            $y = (int) round($this->center + sin($angle) * $r - $type->grid_h / 2);

            $zone = $inside ? 'ic' : 'dis';

            // Hedef bölge dolduysa bina yine de kurulur (boş bir yere).
            // Aksi hâlde Kurgan, Boy Otağı gibi tek nüshalı yapılar hiç
            // yerleştirilmeyebilir.
            if ($this->putNearby($village, $type, $x, $y, $otagLevel, $zone)
                || $this->putNearby($village, $type, $x, $y, $otagLevel, 'serbest')) {
                $placed++;
            }
        }

        return $placed;
    }

    /** Kaynak yapılarını surların dışındaki şeride dizer. */
    private function placeOutside(Village $village, array $list, int $otagLevel): int
    {
        if (! $list) {
            return 0;
        }

        $placed = 0;
        $n = count($list);
        $radius = $this->wallHalf + 3.5;

        foreach ($list as $i => $type) {
            $angle = ($i / $n) * M_PI * 2 + 0.35;
            $r = $radius + (($i % 2) * 2.2);

            $x = (int) round($this->center + cos($angle) * $r - $type->grid_w / 2);
            $y = (int) round($this->center + sin($angle) * $r - $type->grid_h / 2);

            if ($this->putNearby($village, $type, $x, $y, $otagLevel, 'dis')) {
                $placed++;
            }
        }

        return $placed;
    }

    /**
     * Tuzakları kapı önlerine, duvar dibine ve çekirdeğe gömer.
     *
     * Tuzak türleri SIRAYLA değil DÖNÜŞÜMLÜ dağıtılır: aksi hâlde çok sayıda
     * yer tuzağı bütün noktaları kapar ve Hava Kapanı hiç yerleştirilemez —
     * bu da hava ordularını tamamen karşılıksız bırakır.
     *
     * @param  array<int,BuildingType>  $list
     */
    private function placeTraps(Village $village, array $list, int $otagLevel): int
    {
        if (! $list) {
            return 0;
        }

        // Türe göre grupla, sonra dönüşümlü sıraya diz.
        $byKey = [];
        foreach ($list as $type) {
            $byKey[$type->key][] = $type;
        }

        $queue = [];
        while ($byKey) {
            foreach ($byKey as $key => $items) {
                $queue[] = array_shift($byKey[$key]);
                if (! $byKey[$key]) {
                    unset($byKey[$key]);
                }
            }
        }

        $placed = 0;
        $spots = $this->trapSpots();

        foreach ($queue as $i => $type) {
            if (! isset($spots[$i])) {
                break;
            }
            [$x, $y] = $spots[$i];
            if ($this->putNearby($village, $type, $x, $y, $otagLevel, 'serbest')) {
                $placed++;
            }
        }

        return $placed;
    }

    /**
     * Tuzak noktaları: kapı önleri, duvar köşeleri, duvar dibi ve çekirdek.
     *
     * @return array<int,array{0:int,1:int}>
     */
    private function trapSpots(): array
    {
        $c = $this->center;
        $h = $this->wallHalf;

        $spots = [
            // Kapı önleri (saldırganın en çok yoğunlaştığı yer)
            [$c, $c - $h - 1], [$c + 1, $c - $h - 1],
            [$c, $c + $h + 1], [$c + 1, $c + $h + 1],
            // Çekirdek (hava birimleri Otağ'a dalar)
            [$c - 3, $c - 3], [$c + 4, $c + 4], [$c - 3, $c + 4], [$c + 4, $c - 3],
            [$c, $c - 5], [$c, $c + 6], [$c - 6, $c], [$c + 6, $c],
            // Duvar köşeleri (dışta)
            [$c - $h - 2, $c - $h - 2], [$c + $h + 2, $c + $h + 2],
            [$c - $h - 2, $c + $h + 2], [$c + $h + 2, $c - $h - 2],
            // Duvar dibi (içte) — duvarı kıranı karşılar
            [$c - $h + 2, $c], [$c + $h - 2, $c],
            [$c, $c - $h + 2], [$c, $c + $h - 2],
            // Dış şerit
            [$c - 5, $c - $h - 2], [$c + 5, $c + $h + 2],
            [$c - $h - 2, $c - 6], [$c + $h + 2, $c + 6],
            [$c - 8, $c - $h - 1], [$c + 8, $c + $h + 1],
        ];

        return $spots;
    }

    /**
     * İstenen kareye sığmazsa çevresinde spiral tarayarak yerleştirir.
     *
     * @param  string  $zone  'ic' (sur içi), 'dis' (sur dışı) veya 'serbest'
     */
    private function putNearby(Village $village, BuildingType $type, int $x, int $y, int $otagLevel, string $zone): bool
    {
        $level = $this->bestLevelFor($type, $otagLevel);

        for ($r = 0; $r <= 14; $r++) {
            for ($dy = -$r; $dy <= $r; $dy++) {
                for ($dx = -$r; $dx <= $r; $dx++) {
                    if ($r > 0 && max(abs($dx), abs($dy)) !== $r) {
                        continue;
                    }

                    $nx = $x + $dx;
                    $ny = $y + $dy;

                    if (! $this->inZone($type, $nx, $ny, $zone)) {
                        continue;
                    }
                    if ($this->put($village, $type, $nx, $ny, $level)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** Binanın tamamı istenen bölgede mi? */
    private function inZone(BuildingType $type, int $x, int $y, string $zone): bool
    {
        if ($zone === 'serbest') {
            return true;
        }

        $min = $this->center - $this->wallHalf;
        $max = $this->center + $this->wallHalf;

        if ($zone === 'ic') {
            return $x > $min && $y > $min
                && $x + $type->grid_w - 1 < $max
                && $y + $type->grid_h - 1 < $max;
        }

        // 'dis': binanın hiçbir karesi sur karesinin içine girmesin
        $overlapX = $x <= $max && $x + $type->grid_w - 1 >= $min;
        $overlapY = $y <= $max && $y + $type->grid_h - 1 >= $min;

        return ! ($overlapX && $overlapY);
    }

    private function put(Village $village, BuildingType $type, int $x, int $y, int $level): bool
    {
        if (! $this->fits($type, $x, $y)) {
            return false;
        }

        for ($dy = 0; $dy < $type->grid_h; $dy++) {
            for ($dx = 0; $dx < $type->grid_w; $dx++) {
                $this->occupied[$x + $dx][$y + $dy] = true;
            }
        }

        Building::create([
            'village_id'       => $village->id,
            'building_type_id' => $type->id,
            'level'            => max(1, min($type->max_level, $level)),
            'x'                => $x,
            'y'                => $y,
            'is_constructing'  => false,
            'collected_at'     => now(),
        ]);

        return true;
    }

    private function fits(BuildingType $type, int $x, int $y): bool
    {
        if ($x < $this->margin || $y < $this->margin) {
            return false;
        }
        if ($x + $type->grid_w > $this->size - $this->margin) {
            return false;
        }
        if ($y + $type->grid_h > $this->size - $this->margin) {
            return false;
        }

        for ($dy = 0; $dy < $type->grid_h; $dy++) {
            for ($dx = 0; $dx < $type->grid_w; $dx++) {
                if ($this->occupied[$x + $dx][$y + $dy] ?? false) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Otağ seviyesine göre binanın ulaşabileceği en yüksek seviye. */
    private function bestLevelFor(BuildingType $type, int $otagLevel): int
    {
        $best = 1;
        foreach ($type->levels as $lv) {
            if ($lv->requires_otag_level <= $otagLevel) {
                $best = $lv->level;
            }
        }

        return $best;
    }
}
