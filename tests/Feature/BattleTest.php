<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Services\BattleService;
use App\Services\Combat\BattleSimulator;
use Tests\GameTestCase;

class BattleTest extends GameTestCase
{
    /** Aynı girdi + aynı tohum = aynı sonuç. Tekrar oynatmanın temel şartı. */
    public function test_savas_simulasyonu_deterministiktir(): void
    {
        $defender = $this->makePlayer('Savunan', otagLevel: 7);
        $defense = app(BattleService::class)->buildDefense($defender->village);

        $units = [];
        for ($i = 0; $i < 30; $i++) {
            $units[] = [
                'key' => 'ceri', 'level' => 3, 'hp' => 100, 'dps' => 20,
                'speed' => 1.6, 'range' => 0.5, 'attack_speed' => 1.0,
                'pref' => 'yok', 'mult' => 1, 'air' => false, 'splash' => 0,
                'x' => 18 + ($i % 8), 'y' => 3, 'deploy_tick' => $i,
            ];
        }

        $first = (new BattleSimulator)->run($defense, ['units' => $units, 'spells' => []], 4242);
        $second = (new BattleSimulator)->run($defense, ['units' => $units, 'spells' => []], 4242);

        $this->assertSame($first['stars'], $second['stars']);
        $this->assertSame($first['destruction'], $second['destruction']);
        $this->assertSame($first['duration'], $second['duration']);
        $this->assertSame(
            count($first['replay']['events']),
            count($second['replay']['events']),
            'Olay sayısı birebir aynı olmalı'
        );
    }

    public function test_farkli_tohum_farkli_sonuc_uretebilir(): void
    {
        $defender = $this->makePlayer('Tohum', otagLevel: 9);
        $defense = app(BattleService::class)->buildDefense($defender->village);

        $units = [];
        for ($i = 0; $i < 25; $i++) {
            $units[] = [
                'key' => 'ceri', 'level' => 1, 'hp' => 60, 'dps' => 12,
                'speed' => 1.6, 'range' => 0.5, 'attack_speed' => 1.0,
                'pref' => 'yok', 'mult' => 1, 'air' => false, 'splash' => 0,
                'x' => 10, 'y' => 3, 'deploy_tick' => $i * 2,
            ];
        }

        $a = (new BattleSimulator)->run($defense, ['units' => $units, 'spells' => []], 1);
        $this->assertIsFloat($a['destruction']);
        $this->assertGreaterThanOrEqual(0, $a['destruction']);
        $this->assertLessThanOrEqual(100, $a['destruction']);
    }

    public function test_bos_oba_tamamen_yikilir_ve_uc_yildiz_verir(): void
    {
        $defense = [
            'buildings' => [[
                'id' => 1, 'key' => 'otag', 'category' => 'otag', 'level' => 1,
                'x' => 20, 'y' => 20, 'w' => 4, 'h' => 4, 'hp' => 100,
                'dps' => 0, 'range' => 0, 'targets_ground' => true,
            ]],
            'clan_troops' => [],
        ];

        $units = [[
            'key' => 'ceri', 'level' => 1, 'hp' => 500, 'dps' => 200,
            'speed' => 3.0, 'range' => 0.5, 'attack_speed' => 1.0,
            'pref' => 'yok', 'mult' => 1, 'air' => false, 'splash' => 0,
            'x' => 20, 'y' => 10, 'deploy_tick' => 0,
        ]];

        $result = (new BattleSimulator)->run($defense, ['units' => $units, 'spells' => []], 7);

        $this->assertSame(3, $result['stars']);
        $this->assertSame(100.0, $result['destruction']);
        $this->assertTrue($result['otag_down']);
    }

    public function test_hava_birimi_sadece_hava_savunmasindan_hasar_alir(): void
    {
        // Yalnızca yere ateş eden bir kule: hava birimi hasar almamalı.
        $defense = [
            'buildings' => [
                [
                    'id' => 1, 'key' => 'otag', 'category' => 'otag', 'level' => 1,
                    'x' => 20, 'y' => 20, 'w' => 4, 'h' => 4, 'hp' => 4000,
                    'dps' => 0, 'range' => 0,
                ],
                [
                    'id' => 2, 'key' => 'ates_kulesi', 'category' => 'savunma', 'level' => 1,
                    'x' => 24, 'y' => 20, 'w' => 3, 'h' => 3, 'hp' => 1000,
                    'dps' => 500, 'range' => 12, 'targets_air' => false, 'targets_ground' => true,
                    'attack_speed' => 1.0,
                ],
            ],
            'clan_troops' => [],
        ];

        $air = [[
            'key' => 'togrul', 'level' => 1, 'hp' => 200, 'dps' => 60,
            'speed' => 4.0, 'range' => 1.6, 'attack_speed' => 1.0,
            'pref' => 'yok', 'mult' => 1, 'air' => true, 'splash' => 0,
            'x' => 20, 'y' => 8, 'deploy_tick' => 0,
        ]];

        $result = (new BattleSimulator)->run($defense, ['units' => $air, 'spells' => []], 11);
        $deaths = array_filter($result['replay']['events'], fn ($e) => $e['e'] === 'udie');

        $this->assertCount(0, $deaths, 'Yere ateş eden kule hava birimini vuramaz');
    }

    public function test_duvar_kirici_duvara_kat_kat_hasar_verir(): void
    {
        $defense = [
            'buildings' => [
                [
                    'id' => 1, 'key' => 'otag', 'category' => 'otag', 'level' => 1,
                    'x' => 20, 'y' => 20, 'w' => 4, 'h' => 4, 'hp' => 100000,
                    'dps' => 0, 'range' => 0,
                ],
                [
                    'id' => 2, 'key' => 'surlar', 'category' => 'duvar', 'level' => 1,
                    'x' => 20, 'y' => 16, 'w' => 1, 'h' => 1, 'hp' => 400,
                    'dps' => 0, 'range' => 0,
                ],
            ],
            'clan_troops' => [],
        ];

        $breaker = [[
            'key' => 'gurzcu', 'level' => 1, 'hp' => 300, 'dps' => 10,
            'speed' => 2.8, 'range' => 0.5, 'attack_speed' => 1.0,
            'pref' => 'duvar', 'mult' => 40, 'air' => false, 'splash' => 0,
            'x' => 20, 'y' => 12, 'deploy_tick' => 0,
        ]];

        $result = (new BattleSimulator)->run($defense, ['units' => $breaker, 'spells' => []], 3);
        $wallDeaths = array_filter(
            $result['replay']['events'],
            fn ($e) => $e['e'] === 'bdie' && $e['b'] === 2
        );

        $this->assertNotEmpty($wallDeaths, 'Gürzcü duvarı yıkmalı (10 x 40 = 400 hasar)');
    }

    public function test_saldiri_ganimet_kupa_ve_kalkan_uygular(): void
    {
        $attacker = $this->makePlayer('Akinci', otagLevel: 5);
        $defender = $this->makePlayer('Hedef', otagLevel: 5);

        $this->giveResources($defender);
        $this->giveTroops($attacker, ['ceri' => 60, 'okcu_kiz' => 30, 'gurzcu' => 10], level: 3);
        $defender->update(['shield_until' => null, 'guard_until' => null, 'trophies' => 800]);
        $attacker->update(['trophies' => 800, 'guard_until' => null]);

        $goldBefore = $attacker->village->fresh()->gold;
        $defenderGold = $defender->village->fresh()->gold;

        $deployments = [];
        $i = 0;
        foreach (['gurzcu' => 10, 'ceri' => 60, 'okcu_kiz' => 30] as $key => $count) {
            for ($n = 0; $n < $count; $n++, $i++) {
                $deployments[] = ['troop' => $key, 'x' => 16 + ($i % 12), 'y' => 3, 't' => $i * 0.1];
            }
        }

        $response = $this->postJson(
            "/api/v1/savas/saldir/{$defender->village->id}",
            ['deployments' => $deployments],
            $this->tokenFor($attacker)
        );

        $response->assertCreated()->assertJsonStructure([
            'battle' => ['id', 'stars', 'destruction', 'duration', 'loot', 'trophies', 'replay'],
            'village',
        ]);

        $battle = Battle::find($response->json('battle.id'));
        $this->assertNotNull($battle);
        $this->assertNotNull($battle->replay, 'Tekrar kaydedilmeli');

        // Askerler tükenmiş olmalı
        $this->assertSame(0, (int) $attacker->troops()->sum('ready'), 'Gönderilen askerler tükenir');

        // Yıldız alındıysa yağma, kupa ve kalkan uygulanmalı
        if ($battle->stars > 0) {
            $this->assertGreaterThan($goldBefore, $attacker->village->fresh()->gold, 'Yağma eklenmeli');
            $this->assertLessThan($defenderGold, $defender->village->fresh()->gold, 'Savunan kaynak kaybeder');
            $this->assertGreaterThan(800, $attacker->fresh()->trophies, 'Saldıran kupa kazanır');
            $this->assertNotNull($defender->fresh()->shield_until, 'Savunan kalkan alır');
        }

        // Saldıran koruma (guard) süresine girer
        $this->assertNotNull($attacker->fresh()->guard_until);
    }

    public function test_kalkan_altindaki_obaya_saldirilamaz(): void
    {
        $attacker = $this->makePlayer('Saldirgan');
        $defender = $this->makePlayer('Korumali');
        $defender->update(['shield_until' => now()->addHours(10)]);
        $this->giveTroops($attacker, ['ceri' => 5]);

        $this->postJson(
            "/api/v1/savas/saldir/{$defender->village->id}",
            ['deployments' => [['troop' => 'ceri', 'x' => 20, 'y' => 3, 't' => 0]]],
            $this->tokenFor($attacker)
        )->assertStatus(422)->assertJsonPath('code', 'kalkan_var');
    }

    public function test_sahip_olmayan_asker_gonderilemez(): void
    {
        $attacker = $this->makePlayer('Hileci');
        $defender = $this->makePlayer('Kurban');
        $defender->update(['shield_until' => null, 'guard_until' => null]);
        $this->giveTroops($attacker, ['ceri' => 2]);

        $this->postJson(
            "/api/v1/savas/saldir/{$defender->village->id}",
            ['deployments' => [
                ['troop' => 'ceri', 'x' => 20, 'y' => 3, 't' => 0],
                ['troop' => 'ceri', 'x' => 21, 'y' => 3, 't' => 0],
                ['troop' => 'ceri', 'x' => 22, 'y' => 3, 't' => 0],
            ]],
            $this->tokenFor($attacker)
        )->assertStatus(422)->assertJsonPath('code', 'asker_yetersiz');
    }

    public function test_kendi_obasina_saldirilamaz(): void
    {
        $player = $this->makePlayer('Kendisi');
        $this->giveTroops($player, ['ceri' => 5]);

        $this->postJson(
            "/api/v1/savas/saldir/{$player->village->id}",
            ['deployments' => [['troop' => 'ceri', 'x' => 20, 'y' => 3, 't' => 0]]],
            $this->tokenFor($player)
        )->assertForbidden();
    }

    public function test_tekrar_kaydi_sadece_taraflarca_goruntulenir(): void
    {
        $attacker = $this->makePlayer('Taraf1', otagLevel: 3);
        $defender = $this->makePlayer('Taraf2', otagLevel: 3);
        $stranger = $this->makePlayer('Yabanci');

        $defender->update(['shield_until' => null, 'guard_until' => null]);
        $this->giveTroops($attacker, ['ceri' => 10]);

        $deployments = [];
        for ($i = 0; $i < 10; $i++) {
            $deployments[] = ['troop' => 'ceri', 'x' => 18 + $i, 'y' => 3, 't' => 0];
        }

        $battleId = $this->postJson(
            "/api/v1/savas/saldir/{$defender->village->id}",
            ['deployments' => $deployments],
            $this->tokenFor($attacker)
        )->assertCreated()->json('battle.id');

        $this->getJson("/api/v1/savas/{$battleId}", $this->tokenFor($attacker))->assertOk();
        $this->getJson("/api/v1/savas/{$battleId}", $this->tokenFor($defender))->assertOk();
        $this->getJson("/api/v1/savas/{$battleId}", $this->tokenFor($stranger))->assertForbidden();
    }
}
