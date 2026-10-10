<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\BuildingType;
use App\Services\ResourceService;
use Tests\GameTestCase;

class VillageTest extends GameTestCase
{
    public function test_kaynaklar_zamanla_uretilir_ve_depo_siniri_asilmaz(): void
    {
        $player = $this->makePlayer('Uretici');
        $village = $player->village;
        $village->update(['gold' => 0, 'resources_updated_at' => now()->subHour()]);

        $resources = app(ResourceService::class);
        $rates = $resources->productionRates($village);
        $this->assertGreaterThan(0, $rates['gold'], 'Altın madeni üretmeli');

        $resources->sync($village->refresh());

        $this->assertGreaterThan(0, $village->gold, 'Bir saatte altın birikmeli');

        // Depo sınırı
        $caps = $resources->storageCaps($village);
        $village->update(['gold' => 0, 'resources_updated_at' => now()->subYear()]);
        $resources->sync($village->refresh());

        $this->assertLessThanOrEqual($caps['gold'], $village->gold, 'Üretim depo sınırını aşmamalı');
    }

    public function test_bina_kurulur_ve_kaynak_duser(): void
    {
        $player = $this->makePlayer('Insaatci');
        $this->giveResources($player, 500000);

        $before = $player->village->fresh()->gold;

        $response = $this->postJson('/api/v1/oba/bina', [
            'key' => 'ates_kulesi', 'x' => 10, 'y' => 10,
        ], $this->tokenFor($player));

        $response->assertCreated()->assertJsonPath('building.key', 'ates_kulesi');

        $this->assertLessThan($before, $player->village->fresh()->gold, 'Kurulum kaynak harcamalı');
        $this->assertDatabaseHas('buildings', ['village_id' => $player->village->id, 'x' => 10, 'y' => 10]);
    }

    public function test_cakisan_karede_bina_kurulamaz(): void
    {
        $player = $this->makePlayer('Cakisma');
        $this->giveResources($player);

        // Başlangıç düzeninde Otağ (20,20)-(23,23) karelerini kaplar.
        $this->postJson('/api/v1/oba/bina', ['key' => 'ates_kulesi', 'x' => 21, 'y' => 21],
            $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'kare_dolu');
    }

    public function test_izgara_disina_bina_kurulamaz(): void
    {
        $player = $this->makePlayer('Sinir');
        $this->giveResources($player);

        $this->postJson('/api/v1/oba/bina', ['key' => 'ates_kulesi', 'x' => 42, 'y' => 42],
            $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'izgara_disi');
    }

    public function test_kaynak_yetersizse_bina_kurulamaz(): void
    {
        $player = $this->makePlayer('Yoksul');
        $player->village->update(['gold' => 0, 'grain' => 0, 'iron' => 0, 'kut' => 0,
            'resources_updated_at' => now()]);

        $this->postJson('/api/v1/oba/bina', ['key' => 'ates_kulesi', 'x' => 10, 'y' => 10],
            $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'kaynak_yetersiz');
    }

    public function test_otag_seviyesi_adet_sinirini_uygular(): void
    {
        $player = $this->makePlayer('Sinirli');
        $this->giveResources($player);

        // Otağ 1'de Ateş Kulesi sınırı 1 adettir.
        $limit = BuildingType::where('key', 'ates_kulesi')->first()->allowedCount(1);
        $this->assertSame(1, $limit);

        $this->postJson('/api/v1/oba/bina', ['key' => 'ates_kulesi', 'x' => 10, 'y' => 10],
            $this->tokenFor($player))->assertCreated();

        $this->postJson('/api/v1/oba/bina', ['key' => 'ates_kulesi', 'x' => 16, 'y' => 10],
            $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'adet_siniri');
    }

    public function test_bina_yukseltme_insaat_baslatir_ve_zamaninda_biter(): void
    {
        $player = $this->makePlayer('Yukselten');
        $this->giveResources($player);

        $building = $player->village->buildings()
            ->whereHas('type', fn ($q) => $q->where('key', 'tarla'))->first();

        $this->postJson("/api/v1/oba/bina/{$building->id}/yukselt", [], $this->tokenFor($player))
            ->assertOk()
            ->assertJsonPath('building.constructing', true);

        $building->refresh();
        $this->assertTrue($building->is_constructing);
        $this->assertSame(2, $building->target_level);
        $this->assertSame(1, $building->level, 'İnşaat bitene kadar seviye artmaz');

        // Süreyi geçmiş say ve durumu yeniden oku: tembel çözümleme devreye girer.
        $building->update(['construction_ends_at' => now()->subSecond()]);

        $this->getJson('/api/v1/oba', $this->tokenFor($player))->assertOk();

        $building->refresh();
        $this->assertFalse($building->is_constructing);
        $this->assertSame(2, $building->level, 'Süre dolunca seviye artmalı');
    }

    public function test_usta_sayisi_es_zamanli_insaati_sinirlar(): void
    {
        $player = $this->makePlayer('Ustalar');
        $this->giveResources($player);
        $player->village->update(['builders' => 1]);

        $fields = $player->village->buildings()
            ->whereHas('type', fn ($q) => $q->where('key', 'tarla'))->get();

        $this->postJson("/api/v1/oba/bina/{$fields[0]->id}/yukselt", [], $this->tokenFor($player))
            ->assertOk();

        $this->postJson("/api/v1/oba/bina/{$fields[1]->id}/yukselt", [], $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'usta_yok');
    }

    public function test_insaat_iptali_yarim_kaynak_geri_verir(): void
    {
        $player = $this->makePlayer('Iptalci');
        $this->giveResources($player);

        $building = $player->village->buildings()
            ->whereHas('type', fn ($q) => $q->where('key', 'tarla'))->first();

        $this->postJson("/api/v1/oba/bina/{$building->id}/yukselt", [], $this->tokenFor($player))->assertOk();
        $afterSpend = $player->village->fresh()->grain;
        $this->assertGreaterThan(0, $afterSpend);

        $this->postJson("/api/v1/oba/bina/{$building->id}/iptal", [], $this->tokenFor($player))->assertOk();

        $this->assertGreaterThan($afterSpend, $player->village->fresh()->grain, 'İptal kaynak iade etmeli');
        $this->assertFalse($building->fresh()->is_constructing);
    }

    public function test_gok_tasi_ile_insaat_aninda_biter(): void
    {
        $player = $this->makePlayer('Aceleci');
        $this->giveResources($player);
        $player->update(['gok_tasi' => 5000]);

        $building = $player->village->buildings()
            ->whereHas('type', fn ($q) => $q->where('key', 'tarla'))->first();

        $this->postJson("/api/v1/oba/bina/{$building->id}/yukselt", [], $this->tokenFor($player))->assertOk();
        $gemsBefore = $player->fresh()->gok_tasi;

        $this->postJson("/api/v1/oba/bina/{$building->id}/bitir", [], $this->tokenFor($player))->assertOk();

        $this->assertSame(2, $building->fresh()->level);
        $this->assertLessThan($gemsBefore, $player->fresh()->gok_tasi, 'Gök Taşı harcanmalı');
    }

    public function test_baska_oyuncunun_binasi_degistirilemez(): void
    {
        $owner = $this->makePlayer('Sahip');
        $intruder = $this->makePlayer('Davetsiz');
        $this->giveResources($intruder);

        $building = $owner->village->buildings()->first();

        $this->postJson("/api/v1/oba/bina/{$building->id}/yukselt", [], $this->tokenFor($intruder))
            ->assertForbidden()
            ->assertJsonPath('code', 'izin_yok');
    }

    public function test_duzen_kaydetme_cakismada_hicbir_seyi_degistirmez(): void
    {
        $player = $this->makePlayer('Duzenci', otagLevel: 5);
        $buildings = $player->village->buildings()->with('type')->get();
        $a = $buildings[0];
        $b = $buildings[1];
        $originalA = ['x' => $a->x, 'y' => $a->y];

        // İkisini de aynı kareye koymaya çalış
        $this->postJson('/api/v1/oba/duzen', [
            'buildings' => [
                ['id' => $a->id, 'x' => 20, 'y' => 20],
                ['id' => $b->id, 'x' => 20, 'y' => 20],
            ],
        ], $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'kare_dolu');

        $this->assertSame($originalA['x'], $a->fresh()->x, 'Çakışmada hiçbir bina taşınmamalı');
    }

    public function test_duzen_kaydetme_gecerli_tasimayi_uygular(): void
    {
        $player = $this->makePlayer('Duzenci2');
        $building = $player->village->buildings()->with('type')
            ->whereHas('type', fn ($q) => $q->where('key', 'okcu_kulesi'))->first();

        $this->postJson('/api/v1/oba/duzen', [
            'buildings' => [['id' => $building->id, 'x' => 8, 'y' => 8]],
        ], $this->tokenFor($player))->assertOk();

        $building->refresh();
        $this->assertSame(8, $building->x);
        $this->assertSame(8, $building->y);
    }
}
