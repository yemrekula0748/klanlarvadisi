<?php

namespace Database\Seeders;

use App\Models\WorldRegion;
use App\Models\WorldTile;
use App\Services\WorldService;
use Illuminate\Database\Seeder;

/**
 * Dünyaya mitolojik kampları, kaynak damarlarını, harabeleri ve kutsal
 * alanları yerleştirir. Arazi veritabanına yazılmaz (deterministik üretilir);
 * yalnızca "nesneler" saklanır.
 *
 * Tekrar çalıştırılabilir: hedef adede kadar tamamlar, mevcutları çoğaltmaz.
 */
class WorldSeeder extends Seeder
{
    /** Mitolojik düşman kampları. */
    private const CAMPS = [
        ['key' => 'tepegoz_magarasi',   'label' => 'Tepegöz Mağarası',   'power' => [3, 7],  'biomes' => ['dag', 'kar']],
        ['key' => 'karakoncolos_kampi', 'label' => 'Karakoncolos Kampı', 'power' => [4, 8],  'biomes' => ['tayga', 'kar', 'orman']],
        ['key' => 'albasti_ini',        'label' => 'Albastı İni',        'power' => [2, 6],  'biomes' => ['bataklik', 'orman']],
        ['key' => 'yelbegen_yuvasi',    'label' => 'Yelbegen Yuvası',    'power' => [7, 10], 'biomes' => ['dag', 'kar']],
        ['key' => 'sahmeran_cukuru',    'label' => 'Şahmeran Çukuru',    'power' => [5, 9],  'biomes' => ['col', 'bataklik']],
        ['key' => 'erlik_sunagi',       'label' => 'Erlik Sunağı',       'power' => [8, 10], 'biomes' => ['bataklik', 'orman']],
        ['key' => 'yagmaci_obasi',      'label' => 'Yağmacı Obası',      'power' => [1, 5],  'biomes' => ['bozkir', 'cayir', 'col']],
    ];

    private const NODES = [
        ['key' => 'altin_damari', 'label' => 'Altın Damarı', 'res' => 'gold',  'biomes' => ['dag', 'col', 'kar']],
        ['key' => 'demir_damari', 'label' => 'Demir Damarı', 'res' => 'iron',  'biomes' => ['dag', 'tayga', 'kar']],
        ['key' => 'tahil_ovasi',  'label' => 'Tahıl Ovası',  'res' => 'grain', 'biomes' => ['cayir', 'bozkir']],
        ['key' => 'kut_pinari',   'label' => 'Kut Pınarı',   'res' => 'kut',   'biomes' => ['orman', 'bataklik', 'cayir']],
    ];

    private const RUINS = [
        'Eski Kurgan', 'Devrilmiş Taş Baba', 'Yıkık Kervansaray', 'Terk Edilmiş Oba',
        'Damgalı Kaya', 'Göktürk Yazıtı', 'Kırık Tuğ', 'Susmuş Ocak',
    ];

    public function __construct(private readonly WorldService $world) {}

    public function run(): void
    {
        $this->seedSacredSites();
        $this->topUp('ucube_kampi', 900, fn () => $this->makeCamp());
        $this->topUp('kaynak_damari', 700, fn () => $this->makeNode());
        $this->topUp('harabe', 400, fn () => $this->makeRuin());
    }

    /**
     * Türü hedef adede kadar tamamlar.
     *
     * Üretici her çağrıda yerleştiremeyebilir (randomSpot 80 denemede boş
     * kare bulamazsa vazgeçer). Bu yüzden sabit sayıda çağrı yetmez:
     * gerçek sayıyı ölçerek ilerliyoruz, yoksa ilk tohumlama hedefin
     * altında kalıyor ve bir sonraki çalıştırma farkı kapatıyordu — dünya
     * tohumu da böylece "her çalıştırmada değişen" bir şeye dönüşüyordu.
     */
    private function topUp(string $type, int $target, callable $maker): void
    {
        $adet = WorldTile::where('type', $type)->count();

        // Sonsuz döngüye karşı: her eksik için en çok üç deneme.
        $kalanDeneme = max(0, $target - $adet) * 3;

        while ($adet < $target && $kalanDeneme-- > 0) {
            $maker();
            $adet = WorldTile::where('type', $type)->count();
        }
    }

    /**
     * Her bölgenin merkezine, o bölgenin adını taşıyan kutsal alan koyar.
     *
     * Eşleştirme bölgeye göre yapılır, koordinata göre değil: ikinci kez
     * tohumlandığında bölge merkezi artık dolu olduğu için
     * nearestFreeLand() komşu bir kare döndürür, koordinatla eşleşen bir
     * updateOrCreate ise orada ikinci bir kutsal alan açardı. (Açmıştı da:
     * 16 bölge için 32 kayıt oluşuyordu.)
     */
    private function seedSacredSites(): void
    {
        foreach (WorldRegion::all() as $region) {
            $mevcut = WorldTile::where('region_id', $region->id)
                ->where('type', 'kutsal_alan')
                ->first();

            if ($mevcut) {
                // Yeri sabit kalsın, yalnızca bölgeden gelen bilgiler tazelensin.
                $mevcut->update($this->sacredSiteData($region));
                continue;
            }

            [$x, $y] = $this->nearestFreeLand($region->center_x, $region->center_y, 24);
            if ($x === null) {
                continue;
            }

            WorldTile::create(['x' => $x, 'y' => $y] + $this->sacredSiteData($region));
        }
    }

    /** Kutsal alanın bölgeden türeyen alanları. */
    private function sacredSiteData(WorldRegion $region): array
    {
        return [
            'type'      => 'kutsal_alan',
            'region_id' => $region->id,
            'label'     => $region->name,
            'power'     => 10,
            'data'      => [
                'region' => $region->key,
                'bonus'  => $region->bonus,
                'lore'   => $region->lore,
            ],
        ];
    }

    private function makeCamp(): void
    {
        $camp = self::CAMPS[array_rand(self::CAMPS)];
        // Kamplar dağ ve karlı zirvelere de kurulur (oba kurulamaz, kamp kurulur).
        [$x, $y, $biome] = $this->randomSpot($camp['biomes'], requirePassable: false);
        if ($x === null) {
            return;
        }

        WorldTile::updateOrCreate(
            ['x' => $x, 'y' => $y],
            [
                'type'      => 'ucube_kampi',
                'label'     => $camp['label'],
                'power'     => random_int($camp['power'][0], $camp['power'][1]),
                'region_id' => $this->world->regionAt($x, $y)?->id,
                'data'      => ['camp' => $camp['key'], 'biome' => $biome],
            ]
        );
    }

    private function makeNode(): void
    {
        $node = self::NODES[array_rand(self::NODES)];
        [$x, $y, $biome] = $this->randomSpot($node['biomes'], requirePassable: false);
        if ($x === null) {
            return;
        }

        $tier = random_int(1, 3);

        WorldTile::updateOrCreate(
            ['x' => $x, 'y' => $y],
            [
                'type'      => 'kaynak_damari',
                'label'     => $node['label'],
                'power'     => $tier,
                'region_id' => $this->world->regionAt($x, $y)?->id,
                'data'      => [
                    'node'     => $node['key'],
                    'resource' => $node['res'],
                    'per_hour' => $tier * 2500,
                    'biome'    => $biome,
                ],
            ]
        );
    }

    private function makeRuin(): void
    {
        [$x, $y, $biome] = $this->randomSpot(null, requirePassable: true);
        if ($x === null) {
            return;
        }

        WorldTile::updateOrCreate(
            ['x' => $x, 'y' => $y],
            [
                'type'      => 'harabe',
                'label'     => self::RUINS[array_rand(self::RUINS)],
                'power'     => 0,
                'region_id' => $this->world->regionAt($x, $y)?->id,
                'data'      => ['biome' => $biome],
            ]
        );
    }

    /** İstenen arazilerden birinde boş bir kare arar. */
    private function randomSpot(?array $biomes, bool $requirePassable): array
    {
        $w = config('game.world.width');
        $h = config('game.world.height');

        for ($try = 0; $try < 80; $try++) {
            $x = random_int(6, $w - 7);
            $y = random_int(6, $h - 7);
            $terrain = $this->world->terrainAt($x, $y);

            if ($terrain['biome'] === 'deniz' || $terrain['biome'] === 'nehir') {
                continue;
            }
            if ($requirePassable && ! $terrain['passable']) {
                continue;
            }
            if ($biomes && ! in_array($terrain['biome'], $biomes, true)) {
                continue;
            }
            if (WorldTile::where('x', $x)->where('y', $y)->exists()) {
                continue;
            }

            return [$x, $y, $terrain['biome']];
        }

        return [null, null, null];
    }

    /** Merkeze en yakın boş kara karesini spiral tarama ile bulur. */
    private function nearestFreeLand(int $cx, int $cy, int $maxRadius): array
    {
        for ($r = 0; $r <= $maxRadius; $r++) {
            for ($dy = -$r; $dy <= $r; $dy++) {
                for ($dx = -$r; $dx <= $r; $dx++) {
                    if (max(abs($dx), abs($dy)) !== $r) {
                        continue;
                    }

                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    $biome = $this->world->terrainAt($x, $y)['biome'];

                    if ($biome !== 'deniz' && $biome !== 'nehir'
                        && ! WorldTile::where('x', $x)->where('y', $y)->exists()) {
                        return [$x, $y];
                    }
                }
            }
        }

        return [null, null];
    }
}
