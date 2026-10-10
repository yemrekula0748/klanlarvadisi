<?php

namespace Tests\Feature;

use App\Models\Clan;
use App\Services\ClanService;
use App\Services\ClanWarService;
use Tests\GameTestCase;

class ClanTest extends GameTestCase
{
    public function test_boy_kurulur_ve_kurucu_lider_olur(): void
    {
        $player = $this->makePlayer('Kurucu', otagLevel: 5);
        $this->forceResources($player, 200000);

        $this->postJson('/api/v1/boy', [
            'name'        => 'Kayı Boyu',
            'description' => 'Tuğumuz yüksek.',
            'type'        => 'acik',
        ], $this->tokenFor($player))
            ->assertCreated()
            ->assertJsonPath('clan.name', 'Kayı Boyu');

        $player->refresh();
        $this->assertNotNull($player->clan_id);
        $this->assertSame('lider', $player->clan_role);
        $this->assertTrue($player->isClanLeader());
    }

    public function test_ayni_isimde_iki_boy_kurulamaz(): void
    {
        $first = $this->makePlayer('Birinci', otagLevel: 5);
        $second = $this->makePlayer('Ikinci', otagLevel: 5);
        $this->forceResources($first, 200000);
        $this->forceResources($second, 200000);

        $this->postJson('/api/v1/boy', ['name' => 'Salur Boyu'], $this->tokenFor($first))
            ->assertCreated();

        $this->postJson('/api/v1/boy', ['name' => 'Salur Boyu'], $this->tokenFor($second))
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_acik_boya_dogrudan_katilinir(): void
    {
        $leader = $this->makePlayer('Lider', otagLevel: 5);
        $member = $this->makePlayer('Uye');
        $clan = app(ClanService::class)->create($leader, ['name' => 'Avşar Boyu', 'type' => 'acik']);

        $this->postJson("/api/v1/boy/{$clan->id}/katil", [], $this->tokenFor($member))
            ->assertOk()->assertJsonPath('status', 'katildi');

        $this->assertSame($clan->id, $member->fresh()->clan_id);
        $this->assertSame(2, $clan->fresh()->member_count);
    }

    public function test_davetli_boya_basvuru_gider(): void
    {
        $leader = $this->makePlayer('Lider2', otagLevel: 5);
        $member = $this->makePlayer('Basvuran');
        $clan = app(ClanService::class)->create($leader, ['name' => 'Kınık Boyu', 'type' => 'davet']);

        $this->postJson("/api/v1/boy/{$clan->id}/katil", ['message' => 'Alın beni'],
            $this->tokenFor($member))
            ->assertOk()->assertJsonPath('status', 'istek_gonderildi');

        $this->assertNull($member->fresh()->clan_id);
        $this->assertDatabaseHas('clan_join_requests', [
            'clan_id' => $clan->id, 'player_id' => $member->id, 'status' => 'bekliyor',
        ]);
    }

    public function test_kupa_sarti_saglanmazsa_katilinamaz(): void
    {
        $leader = $this->makePlayer('Secici', otagLevel: 5);
        $weak = $this->makePlayer('Zayif');
        $weak->update(['trophies' => 100]);

        $clan = app(ClanService::class)->create($leader, [
            'name' => 'Bayındır Boyu', 'type' => 'acik', 'required_trophies' => 2000,
        ]);

        $this->postJson("/api/v1/boy/{$clan->id}/katil", [], $this->tokenFor($weak))
            ->assertStatus(422)->assertJsonPath('code', 'kupa_yetersiz');
    }

    public function test_er_rutbeli_uye_uye_cikaramaz(): void
    {
        $leader = $this->makePlayer('Lider3', otagLevel: 5);
        $a = $this->makePlayer('ErBir');
        $b = $this->makePlayer('ErIki');
        $clan = app(ClanService::class)->create($leader, ['name' => 'Döğer Boyu', 'type' => 'acik']);

        app(ClanService::class)->addMember($a, $clan);
        app(ClanService::class)->addMember($b, $clan->refresh());

        $this->postJson("/api/v1/boy/cikar/{$b->id}", [], $this->tokenFor($a->refresh()))
            ->assertForbidden();

        $this->assertSame($clan->id, $b->fresh()->clan_id);
    }

    public function test_lider_ayrilirsa_liderlik_devreder(): void
    {
        $leader = $this->makePlayer('GidenLider', otagLevel: 5);
        $member = $this->makePlayer('KalanUye');
        $clan = app(ClanService::class)->create($leader, ['name' => 'Yazır Boyu', 'type' => 'acik']);
        app(ClanService::class)->addMember($member, $clan);

        $this->postJson('/api/v1/boy/ayril', [], $this->tokenFor($leader))->assertOk();

        $clan->refresh();
        $this->assertSame(1, $clan->member_count);
        $this->assertSame($member->id, $clan->leader_player_id);
        $this->assertSame('lider', $member->fresh()->clan_role);
    }

    public function test_son_uye_ayrilirsa_boy_silinir(): void
    {
        $leader = $this->makePlayer('TekBasina', otagLevel: 5);
        $clan = app(ClanService::class)->create($leader, ['name' => 'Çepni Boyu', 'type' => 'acik']);
        $clanId = $clan->id;

        $this->postJson('/api/v1/boy/ayril', [], $this->tokenFor($leader))->assertOk();

        $this->assertDatabaseMissing('clans', ['id' => $clanId]);
    }

    public function test_boy_sohbeti_yazilir_ve_okunur(): void
    {
        $leader = $this->makePlayer('Sohbetci', otagLevel: 5);
        $clan = app(ClanService::class)->create($leader, ['name' => 'Eymür Boyu', 'type' => 'acik']);

        $this->postJson('/api/v1/boy/sohbet', ['body' => 'Yarın savaş var yiğitler!'],
            $this->tokenFor($leader))->assertCreated();

        $this->getJson('/api/v1/boy/sohbet', $this->tokenFor($leader))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Yarın savaş var yiğitler!']);
    }

    public function test_bagis_asker_aktarir_ve_sayaclari_gunceller(): void
    {
        $receiver = $this->makePlayer('Isteyen', otagLevel: 5);
        $donor = $this->makePlayer('Bagisci', otagLevel: 5);
        $clan = app(ClanService::class)->create($receiver, ['name' => 'Karkın Boyu', 'type' => 'acik']);
        app(ClanService::class)->addMember($donor, $clan);

        $this->giveTroops($donor, ['ceri' => 10]);

        $requestId = $this->postJson('/api/v1/boy/asker-iste', ['message' => 'Asker lazım'],
            $this->tokenFor($receiver))->assertCreated()->json('request.id');

        $this->postJson("/api/v1/boy/bagis/{$requestId}",
            ['troop' => 'ceri', 'quantity' => 4], $this->tokenFor($donor))->assertOk();

        $this->assertSame(6, (int) $donor->troops()->sum('ready'), 'Bağışlanan asker düşer');
        $this->assertSame(4, (int) $receiver->village->clanTroops()->sum('quantity'),
            'Boy Otağı\'na asker girer');
        $this->assertSame(4, $donor->fresh()->donated);
        $this->assertSame(4, $receiver->fresh()->received);
    }

    public function test_boy_savasi_ilan_edilir_ve_hazirlik_gunu_baslar(): void
    {
        $clanA = $this->buildClan('Kargın', 'A', 5);
        $clanB = $this->buildClan('Peçenek', 'B', 5);

        $war = app(ClanWarService::class)->declare($clanA, $clanB, 5);

        $this->assertSame('hazirlik', $war->state);
        $this->assertSame(10, $war->members()->count(), 'Her iki taraftan 5 üye');
        $this->assertTrue($war->prep_ends_at->isFuture());

        // Hazırlık günü bitince savaş günü başlar
        $war->update(['prep_ends_at' => now()->subMinute()]);
        app(ClanWarService::class)->tick();

        $this->assertSame('savas', $war->fresh()->state);
    }

    public function test_savas_gunu_olmadan_savas_saldirisi_yapilamaz(): void
    {
        $clanA = $this->buildClan('Kızık', 'C', 5);
        $clanB = $this->buildClan('Beğdili', 'D', 5);
        $war = app(ClanWarService::class)->declare($clanA, $clanB, 5);

        $attacker = $clanA->members()->first();
        $defender = $clanB->members()->first();
        $this->giveTroops($attacker, ['ceri' => 10]);

        $this->postJson("/api/v1/boy-savasi/{$war->id}/saldir/{$defender->id}",
            ['deployments' => [['troop' => 'ceri', 'x' => 20, 'y' => 3, 't' => 0]]],
            $this->tokenFor($attacker))
            ->assertStatus(422)
            ->assertJsonPath('code', 'savas_gunu_degil');
    }

    /** Verilen üye sayısıyla bir boy kurar. */
    private function buildClan(string $name, string $suffix, int $members): Clan
    {
        $leader = $this->makePlayer("Lider{$suffix}", otagLevel: 5);
        $clan = app(ClanService::class)->create($leader, ['name' => "{$name} Boyu", 'type' => 'acik']);

        for ($i = 1; $i < $members; $i++) {
            $member = $this->makePlayer("Uye{$suffix}{$i}", otagLevel: 5);
            app(ClanService::class)->addMember($member, $clan->refresh());
        }

        return $clan->refresh();
    }
}
