<?php

namespace Tests\Feature;

use App\Models\WorldRegion;
use App\Models\WorldTile;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\WorldSeeder;
use Tests\GameTestCase;

/**
 * Tohumlayıcılar yeniden çalıştırılabilir olmalı: kur.sh her kurulumda
 * `db:seed` çağırıyor ve kullanıcı betiği birden çok kez çalıştırabiliyor.
 * Tekrar tohumlamanın dünyayı çoğaltmadığını güvenceye alıyoruz.
 */
class SeederTest extends GameTestCase
{
    public function test_dunya_tohumu_tekrarlandiginda_cogalmaz(): void
    {
        $this->seed(WorldSeeder::class);

        $kutsalIlk = WorldTile::where('type', 'kutsal_alan')->count();
        $toplamIlk = WorldTile::count();

        $this->assertSame(WorldRegion::count(), $kutsalIlk,
            'Her bölgeye tam bir kutsal alan düşmeli.');

        $this->seed(WorldSeeder::class);

        $this->assertSame($kutsalIlk, WorldTile::where('type', 'kutsal_alan')->count(),
            'İkinci tohumlama kutsal alanları çoğalttı.');
        $this->assertSame($toplamIlk, WorldTile::count(),
            'İkinci tohumlama dünya nesnelerini çoğalttı.');
    }

    public function test_kutsal_alanin_yeri_tekrar_tohumda_oynamaz(): void
    {
        $this->seed(WorldSeeder::class);

        $once = WorldTile::where('type', 'kutsal_alan')
            ->orderBy('region_id')->pluck('x', 'region_id')->all();

        $this->seed(WorldSeeder::class);

        $sonra = WorldTile::where('type', 'kutsal_alan')
            ->orderBy('region_id')->pluck('x', 'region_id')->all();

        $this->assertSame($once, $sonra);
    }

    public function test_katalog_tohumu_tekrarlandiginda_cogalmaz(): void
    {
        // GameTestCase kurulumda bir kez tohumladı; ikincisi çoğaltmamalı.
        $bina = \App\Models\BuildingType::count();
        $asker = \App\Models\TroopType::count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($bina, \App\Models\BuildingType::count());
        $this->assertSame($asker, \App\Models\TroopType::count());
    }
}
