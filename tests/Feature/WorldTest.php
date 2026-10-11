<?php

namespace Tests\Feature;

use App\Services\WorldService;
use App\Support\Noise;
use Tests\GameTestCase;

class WorldTest extends GameTestCase
{
    public function test_arazi_ayni_tohumla_deterministiktir(): void
    {
        $world = app(WorldService::class);

        $first = [];
        $second = [];
        for ($i = 0; $i < 50; $i++) {
            $x = 400 + $i * 7;
            $y = 600 + $i * 11;
            $first[] = $world->terrainAt($x, $y);
        }

        // Yeni bir örnek: aynı tohum, aynı sonuç
        $fresh = new WorldService;
        for ($i = 0; $i < 50; $i++) {
            $x = 400 + $i * 7;
            $y = 600 + $i * 11;
            $second[] = $fresh->terrainAt($x, $y);
        }

        $this->assertSame($first, $second, 'Arazi üretimi deterministik olmalı');
    }

    public function test_gurultu_degerleri_sinirlar_icinde(): void
    {
        $noise = new Noise(12345);

        for ($i = 0; $i < 200; $i++) {
            $v = $noise->fbm($i * 0.37, $i * 0.51);
            $this->assertGreaterThanOrEqual(0.0, $v);
            $this->assertLessThanOrEqual(1.0, $v);

            $r = $noise->ridge($i * 0.13, $i * 0.29);
            $this->assertGreaterThanOrEqual(0.0, $r);
            $this->assertLessThanOrEqual(1.0, $r);
        }
    }

    public function test_adi_olan_bolgeler_kendi_arazisini_sekillendirir(): void
    {
        $world = app(WorldService::class);
        $w = config('game.world.width');
        $h = config('game.world.height');

        // Tek bir merkez karesine bakmak yetmiyor: çekirdeğinin %78'i çayır
        // olan bir bölge, merkezdeki tek bozkır karesi yüzünden "uyuyor"
        // görünebiliyordu. Bölgenin çekirdeğini örnekleyip ilan edilen
        // biyomun orada BASKIN olmasını arıyoruz.
        $uyusmayanlar = [];

        foreach (config('regions') as $region) {
            $cx = (int) round($region['at'][0] * $w);
            $cy = (int) round($region['at'][1] * $h);
            $yaricap = (int) round($region['radius'] * $w * 0.5);

            $sayim = [];
            for ($dy = -$yaricap; $dy <= $yaricap; $dy += max(1, (int) ($yaricap / 6))) {
                for ($dx = -$yaricap; $dx <= $yaricap; $dx += max(1, (int) ($yaricap / 6))) {
                    if ($dx * $dx + $dy * $dy > $yaricap * $yaricap) {
                        continue;
                    }
                    $biyom = $world->terrainAt($cx + $dx, $cy + $dy)['biome'];
                    $sayim[$biyom] = ($sayim[$biyom] ?? 0) + 1;
                }
            }

            // Nehirler her biyomun üstünden geçebilir; baskınlık ölçümünde sayma.
            unset($sayim['nehir']);
            arsort($sayim);
            $baskin = array_key_first($sayim);

            if ($baskin !== $region['biome']) {
                $uyusmayanlar[] = sprintf(
                    '%s: ilan "%s", çekirdekte baskın "%s"',
                    $region['name'], $region['biome'], $baskin
                );
            }
        }

        $this->assertSame([], $uyusmayanlar, "Bölgeler kendi arazisinde değil:\n".implode("\n", $uyusmayanlar));
    }

    public function test_deniz_karesine_oba_kurulamaz(): void
    {
        $world = app(WorldService::class);

        // Haritanın köşesi kesin denizdir (kenar maskesi).
        $this->assertFalse($world->isBuildable(1, 1));
        $this->assertFalse($world->isBuildable(0, 0));
    }

    public function test_yeni_oyuncular_farkli_karelere_dusuyor(): void
    {
        $positions = [];

        for ($i = 0; $i < 8; $i++) {
            $player = $this->makePlayer("Yerlesen{$i}");
            $key = $player->village->world_x.':'.$player->village->world_y;
            $this->assertArrayNotHasKey($key, $positions, 'İki oba aynı kareye kurulmamalı');
            $positions[$key] = true;

            $terrain = app(WorldService::class)
                ->terrainAt($player->village->world_x, $player->village->world_y);
            $this->assertTrue($terrain['passable'], 'Oba geçilebilir araziye kurulmalı');
        }

        $this->assertCount(8, $positions);
    }

    public function test_dunya_bilgisi_ucu_tohum_ve_bolgeleri_dondurur(): void
    {
        $player = $this->makePlayer('Gezgin');
        $this->seed(\Database\Seeders\WorldSeeder::class);

        $this->getJson('/api/v1/dunya/bilgi', $this->tokenFor($player))
            ->assertOk()
            ->assertJsonPath('seed', config('game.world.seed'))
            ->assertJsonPath('width', config('game.world.width'))
            ->assertJsonStructure(['seed', 'width', 'height', 'biomes', 'regions']);
    }

    public function test_dunya_bolge_ucu_dolu_kareleri_dondurur(): void
    {
        $player = $this->makePlayer('Haritaci');
        $v = $player->village;

        $response = $this->getJson(
            "/api/v1/dunya/bolge?x=".max(0, $v->world_x - 8)."&y=".max(0, $v->world_y - 8)."&w=16&h=16",
            $this->tokenFor($player)
        )->assertOk();

        $tiles = collect($response->json('tiles'));
        $mine = $tiles->firstWhere('x', $v->world_x);

        $this->assertNotNull($mine, 'Kendi obam haritada görünmeli');
        $this->assertSame('oba', $mine['type']);
        $this->assertSame($player->name, $mine['player']['name']);
    }

    public function test_kare_ucu_arazi_ve_bolge_bilgisi_verir(): void
    {
        $player = $this->makePlayer('Kareci');

        $this->getJson("/api/v1/dunya/kare?x=800&y=800", $this->tokenFor($player))
            ->assertOk()
            ->assertJsonStructure(['terrain' => ['biome', 'elevation', 'passable']]);
    }

    public function test_bolge_istegi_ust_sinirla_kisitlanir(): void
    {
        $player = $this->makePlayer('Oburr');

        // 64'ten büyük istek doğrulama ile reddedilir
        $this->getJson('/api/v1/dunya/bolge?x=0&y=0&w=500&h=500', $this->tokenFor($player))
            ->assertStatus(422);
    }
}
