<?php

namespace Tests\Feature;

use App\Models\TrainingQueueItem;
use App\Services\TrainingService;
use Tests\GameTestCase;

class ArmyTest extends GameTestCase
{
    public function test_asker_egitimi_kuyruga_girer_ve_kaynak_duser(): void
    {
        $player = $this->makePlayer('Egitici', otagLevel: 5);
        $this->giveResources($player);

        $before = $player->village->fresh()->grain;

        $this->postJson('/api/v1/ordu/egit', ['troop' => 'ceri', 'quantity' => 5],
            $this->tokenFor($player))
            ->assertCreated()
            ->assertJsonPath('queues.training.0.key', 'ceri')
            ->assertJsonPath('queues.training.0.quantity', 5);

        $this->assertLessThan($before, $player->village->fresh()->grain);
        $this->assertDatabaseCount('training_queue', 1);
    }

    public function test_egitim_suresi_dolunca_asker_hazir_olur(): void
    {
        $player = $this->makePlayer('Bekleyen', otagLevel: 5);
        $this->giveResources($player);

        app(TrainingService::class)->train($player->village, 'ceri', 3);

        $item = TrainingQueueItem::first();
        $this->assertNotNull($item);

        // Üç askerin eğitimi tamamlanmış gibi zamanı geri al
        $item->update(['starts_at' => now()->subSeconds($item->seconds_each * 3 + 1)]);

        $this->getJson('/api/v1/oba', $this->tokenFor($player))->assertOk();

        $this->assertSame(3, (int) $player->troops()
            ->whereHas('type', fn ($q) => $q->where('key', 'ceri'))->value('ready'));
        $this->assertDatabaseCount('training_queue', 0);
    }

    public function test_ordugah_kapasitesi_asilamaz(): void
    {
        $player = $this->makePlayer('Dolu', otagLevel: 3);
        $this->giveResources($player);

        $capacity = app(TrainingService::class)->armyCapacity($player->village);
        $this->assertGreaterThan(0, $capacity);

        $this->postJson('/api/v1/ordu/egit',
            ['troop' => 'ceri', 'quantity' => $capacity + 10],
            $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'ordu_dolu');
    }

    public function test_kisla_seviyesi_yetersizse_asker_egitilemez(): void
    {
        $player = $this->makePlayer('Acemi');  // Otağ 1, Kışla 1
        $this->giveResources($player);

        // Tepegöz Kışla 5 ister
        $this->postJson('/api/v1/ordu/egit', ['troop' => 'tepegoz', 'quantity' => 1],
            $this->tokenFor($player))
            ->assertStatus(422)
            ->assertJsonPath('code', 'kisla_seviyesi');
    }

    public function test_egitim_iptali_kaynagi_tam_iade_eder(): void
    {
        $player = $this->makePlayer('Vazgecen', otagLevel: 5);
        $this->giveResources($player);

        $before = $player->village->fresh()->grain;
        app(TrainingService::class)->train($player->village->refresh(), 'ceri', 4);
        $afterSpend = $player->village->fresh()->grain;
        $this->assertLessThan($before, $afterSpend);

        $item = TrainingQueueItem::first();

        $this->deleteJson("/api/v1/ordu/kuyruk/{$item->id}", [], $this->tokenFor($player))->assertOk();

        $this->assertSame($before, (int) $player->village->fresh()->grain, 'Tam iade olmalı');
        $this->assertDatabaseCount('training_queue', 0);
    }

    public function test_demirci_seviyesi_asker_gelistirmeyi_sinirlar(): void
    {
        $player = $this->makePlayer('Demirsiz', otagLevel: 3);
        $this->giveResources($player);

        // Demirci 1. seviyedeyken 2. seviyeye yükseltme için Demirci 2 gerekir
        $response = $this->postJson('/api/v1/ordu/gelistir', ['troop' => 'ceri'],
            $this->tokenFor($player));

        $response->assertStatus(422);
        $this->assertContains($response->json('code'), ['demirci_seviyesi', 'kaynak_yetersiz']);
    }

    public function test_tilsim_hazirlanir(): void
    {
        $player = $this->makePlayer('Kam', otagLevel: 9);
        $this->giveResources($player);

        $this->postJson('/api/v1/ordu/tilsim', ['spell' => 'yildirim', 'quantity' => 1],
            $this->tokenFor($player))
            ->assertCreated()
            ->assertJsonPath('queues.brewing.0.key', 'yildirim');
    }

    public function test_kahraman_otag_seviyesi_yetersizse_uyandirilamaz(): void
    {
        $player = $this->makePlayer('Erken', otagLevel: 5);
        $this->giveResources($player);

        $this->postJson('/api/v1/ordu/kahraman/uyandir', ['hero' => 'oguz_kagan'],
            $this->tokenFor($player))
            ->assertStatus(422);
    }

    public function test_kahraman_kurgan_ile_uyandirilir(): void
    {
        $player = $this->makePlayer('Kagan', otagLevel: 9);
        $this->forceResources($player, 5_000_000);

        $hasKurgan = $player->village->buildings()
            ->whereHas('type', fn ($q) => $q->where('key', 'kurgan'))->exists();
        $this->assertTrue($hasKurgan, 'Otağ 9 düzeninde Kurgan olmalı');

        $this->postJson('/api/v1/ordu/kahraman/uyandir', ['hero' => 'oguz_kagan'],
            $this->tokenFor($player))->assertOk();

        $hero = $player->heroes()->with('type')->get()
            ->firstWhere(fn ($h) => $h->type->key === 'oguz_kagan');

        $this->assertTrue($hero->unlocked);
    }
}
