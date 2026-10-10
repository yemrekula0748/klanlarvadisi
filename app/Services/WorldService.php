<?php

namespace App\Services;

use App\Models\Village;
use App\Models\WorldRegion;
use App\Models\WorldTile;
use App\Support\Noise;
use Illuminate\Support\Collection;

/**
 * Dünya haritası: 1600 x 1600 kare (2.56 milyon). Arazi veritabanında
 * tutulmaz; `Noise` ile deterministik üretilir. Yalnızca obalar, kaynak
 * damarları, mitolojik kamplar ve harabeler `world_tiles` içinde saklanır.
 */
class WorldService
{
    public const BIOMES = [
        'deniz'     => ['name' => 'Kök Deniz',       'color' => '#1b4a6b', 'passable' => false],
        'kiyi'      => ['name' => 'Kıyı',            'color' => '#c9b382', 'passable' => true],
        'bozkir'    => ['name' => 'Bozkır',          'color' => '#8fa05a', 'passable' => true],
        'cayir'     => ['name' => 'Çayır',           'color' => '#6f9b4a', 'passable' => true],
        'orman'     => ['name' => 'Karanlık Orman',  'color' => '#2f5d3a', 'passable' => true],
        'tayga'     => ['name' => 'Tayga',           'color' => '#3b6b52', 'passable' => true],
        'dag'       => ['name' => 'Altay Dağları',   'color' => '#7d7569', 'passable' => false],
        'kar'       => ['name' => 'Karlı Zirve',     'color' => '#e8eef2', 'passable' => false],
        'col'       => ['name' => 'Kızıl Çöl',       'color' => '#c58a4a', 'passable' => true],
        'bataklik'  => ['name' => 'Bataklık',        'color' => '#55603a', 'passable' => true],
        'nehir'     => ['name' => 'Nehir',           'color' => '#2d6f93', 'passable' => false],
    ];

    private Noise $noise;

    /** @var array<int,array{cx:float,cy:float,r:float,e:float,t:float,m:float,s:float}> */
    private array $shapers = [];

    public function __construct()
    {
        $this->noise = new Noise(config('game.world.seed'));
        $this->shapers = $this->loadShapers();
    }

    /**
     * Bölgelerin araziyi büken etkileri. `config/regions.php` tek doğruluk
     * kaynağıdır; `php artisan game:export-unity` ile Unity'ye aktarılır.
     */
    private function loadShapers(): array
    {
        $w = config('game.world.width');
        $h = config('game.world.height');
        $out = [];

        foreach (config('regions', []) as $r) {
            if (empty($r['terrain'])) {
                continue;
            }
            $out[] = [
                'cx' => $r['at'][0] * $w,
                'cy' => $r['at'][1] * $h,
                'r'  => $r['radius'] * min($w, $h),
                'e'  => (float) $r['terrain']['e'],
                't'  => (float) $r['terrain']['t'],
                'm'  => (float) $r['terrain']['m'],
                's'  => (float) ($r['terrain']['strength'] ?? 0.8),
            ];
        }

        return $out;
    }

    public function noise(): Noise
    {
        return $this->noise;
    }

    /** Değer dağılımını [0,1] aralığına yayar (kontrast). */
    private static function stretch(float $v, float $k): float
    {
        $v = ($v - 0.5) * $k + 0.5;

        return max(0.0, min(1.0, $v));
    }

    private static function smoothstep(float $t): float
    {
        $t = max(0.0, min(1.0, $t));

        return $t * $t * (3.0 - 2.0 * $t);
    }

    /**
     * Bir karenin arazisini hesaplar. Unity istemcisindeki
     * `WorldTerrain.Sample` ile birebir aynı sonucu verir.
     */
    public function terrainAt(int $x, int $y): array
    {
        $w = config('game.world.width');
        $h = config('game.world.height');

        // Kenar maskesi: haritanın iç kısmı kara, kenarları Kök Deniz.
        $nx = ($x / $w) * 2 - 1;
        $ny = ($y / $h) * 2 - 1;
        $edge = max(abs($nx), abs($ny));
        $mask = self::smoothstep((1.0 - $edge) / 0.26);

        $continent = self::stretch($this->noise->fbm($x / 260.0, $y / 260.0, 5, 2.0, 0.5, 11), 1.9);
        $elevation = $continent * $mask;

        // Sıcaklık: kuzeyden güneye artar, yükseklikle düşer.
        $temperature = self::stretch(
            1.0 - ($y / $h) + ($this->noise->fbm($x / 170.0, $y / 170.0, 3, 2.0, 0.5, 23) - 0.5) * 0.30,
            1.15
        ) - $elevation * 0.28;
        $temperature = max(0.0, min(1.0, $temperature));

        // Nem: iki ölçekli gürültü.
        $moisture = self::stretch(
            $this->noise->fbm($x / 150.0, $y / 150.0, 4, 2.0, 0.5, 37) * 0.78
            + $this->noise->fbm($x / 430.0, $y / 430.0, 2, 2.0, 0.5, 71) * 0.22,
            2.3
        );

        // Adı olan bölgeler araziyi kendi karakterine büker:
        // Kızıl Çöl gerçekten çöl, Tanrı Dağı gerçekten karlı olur.
        foreach ($this->shapers as $s) {
            $d = sqrt(($x - $s['cx']) ** 2 + ($y - $s['cy']) ** 2);
            if ($d >= $s['r']) {
                continue;
            }
            $wgt = self::smoothstep(1.0 - $d / $s['r']) * $s['s'];
            $elevation += ($s['e'] - $elevation) * $wgt;
            $temperature += ($s['t'] - $temperature) * $wgt;
            $moisture += ($s['m'] - $moisture) * $wgt;
        }

        $elevation = max(0.0, min(1.0, $elevation * $mask + $elevation * (1 - $mask) * $mask));
        $temperature = max(0.0, min(1.0, $temperature));
        $moisture = max(0.0, min(1.0, $moisture));

        // Nehirler: sırt deseninin keskin tepeleri.
        $isRiver = $this->noise->ridge($x / 280.0, $y / 280.0, 4, 53) > 0.9715
            && $elevation > 0.20
            && $elevation < 0.70;

        $biome = $this->classify($elevation, $temperature, $moisture, $isRiver);

        return [
            'x'           => $x,
            'y'           => $y,
            'biome'       => $biome,
            'elevation'   => round($elevation, 4),
            'temperature' => round($temperature, 4),
            'moisture'    => round($moisture, 4),
            'passable'    => self::BIOMES[$biome]['passable'],
        ];
    }

    private function classify(float $e, float $t, float $m, bool $river): string
    {
        if ($e < 0.17) {
            return 'deniz';
        }
        if ($e < 0.21) {
            return 'kiyi';
        }
        if ($e > 0.87) {
            return 'kar';
        }
        if ($e > 0.70) {
            return 'dag';
        }
        if ($river) {
            return 'nehir';
        }
        if ($t < 0.22) {
            return $m > 0.52 ? 'tayga' : 'bozkir';
        }
        if ($t > 0.68 && $m < 0.40) {
            return 'col';
        }
        if ($m > 0.64) {
            return $e < 0.29 ? 'bataklik' : 'orman';
        }
        if ($m > 0.46) {
            return 'cayir';
        }

        return 'bozkir';
    }

    public function isBuildable(int $x, int $y): bool
    {
        $w = config('game.world.width');
        $h = config('game.world.height');

        if ($x < 2 || $y < 2 || $x >= $w - 2 || $y >= $h - 2) {
            return false;
        }

        return $this->terrainAt($x, $y)['passable'];
    }

    /** Yeni oyuncu için boş, uygun bir kare bulur (merkezden dışa doğru spiral). */
    public function findSpawn(): array
    {
        $w = config('game.world.width');
        $h = config('game.world.height');
        $cx = intdiv($w, 2);
        $cy = intdiv($h, 2);
        $minDist = config('game.world.village_min_distance');

        // Altın oran açılı spiral: oyuncuları merkez çevresine homojen yayar.
        $count = WorldTile::where('type', 'oba')->count();
        for ($i = $count; $i < $count + 6000; $i++) {
            $angle = $i * 2.39996323;
            $radius = config('game.world.spawn_ring') * sqrt($i + 1) / 6.0;
            $x = (int) round($cx + cos($angle) * $radius);
            $y = (int) round($cy + sin($angle) * $radius);

            if (! $this->isBuildable($x, $y)) {
                continue;
            }
            if ($this->hasNeighbourWithin($x, $y, $minDist)) {
                continue;
            }

            return [$x, $y, $this->terrainAt($x, $y)['biome']];
        }

        // Çok yoğun dünya: rastgele tarama.
        for ($i = 0; $i < 20000; $i++) {
            $x = random_int(4, $w - 5);
            $y = random_int(4, $h - 5);
            if ($this->isBuildable($x, $y) && ! $this->hasNeighbourWithin($x, $y, $minDist)) {
                return [$x, $y, $this->terrainAt($x, $y)['biome']];
            }
        }

        throw new \RuntimeException('Dünya haritasında boş yer kalmadı.');
    }

    private function hasNeighbourWithin(int $x, int $y, int $d): bool
    {
        return WorldTile::whereBetween('x', [$x - $d, $x + $d])
            ->whereBetween('y', [$y - $d, $y + $d])
            ->exists();
    }

    /**
     * İstemcinin istediği dikdörtgen alandaki dolu kareleri döndürür.
     * Arazi istemcide üretildiği için yalnızca "nesneler" gönderilir.
     */
    public function chunk(int $x, int $y, int $w, int $h): Collection
    {
        $max = (int) sqrt(config('game.world.max_chunk_tiles'));
        $w = min($w, $max);
        $h = min($h, $max);

        return WorldTile::with([
            'village:id,name,otag_level,player_id',
            'player:id,name,trophies,clan_id,shield_until,guard_until',
            'player.clan:id,name,tag,badge',
        ])
            ->whereBetween('x', [$x, $x + $w])
            ->whereBetween('y', [$y, $y + $h])
            ->get()
            ->map(fn (WorldTile $t) => $this->presentTile($t));
    }

    public function presentTile(WorldTile $t): array
    {
        $base = [
            'x'     => $t->x,
            'y'     => $t->y,
            'type'  => $t->type,
            'label' => $t->label,
            'power' => $t->power,
            'data'  => $t->data,
        ];

        if ($t->type === 'oba' && $t->village) {
            $base['village'] = [
                'id'          => $t->village->id,
                'name'        => $t->village->name,
                'otag_level'  => $t->village->otag_level,
            ];
            $base['player'] = [
                'id'        => $t->player?->id,
                'name'      => $t->player?->name,
                'trophies'  => $t->player?->trophies,
                'protected' => (bool) $t->player?->isProtected(),
                'clan'      => $t->player?->clan ? [
                    'id'    => $t->player->clan->id,
                    'name'  => $t->player->clan->name,
                    'tag'   => $t->player->clan->tag,
                    'badge' => $t->player->clan->badge,
                ] : null,
            ];
        }

        return $base;
    }

    public function regionAt(int $x, int $y): ?WorldRegion
    {
        return WorldRegion::all()->first(fn (WorldRegion $r) => $r->contains($x, $y));
    }

    /** Obayı dünya haritasına yerleştirir. */
    public function placeVillage(Village $village): WorldTile
    {
        return WorldTile::updateOrCreate(
            ['x' => $village->world_x, 'y' => $village->world_y],
            [
                'type'       => 'oba',
                'player_id'  => $village->player_id,
                'village_id' => $village->id,
                'clan_id'    => $village->player->clan_id,
                'region_id'  => $this->regionAt($village->world_x, $village->world_y)?->id,
                'label'      => $village->name,
            ]
        );
    }
}
