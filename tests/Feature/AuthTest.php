<?php

namespace Tests\Feature;

use App\Models\Player;
use Tests\GameTestCase;

class AuthTest extends GameTestCase
{
    public function test_kayit_oyuncu_ve_oba_olusturur(): void
    {
        $response = $this->postJson('/api/v1/kayit', [
            'email'       => 'bilge@test.tr',
            'password'    => 'cokgizlisifre',
            'player_name' => 'BilgeKağan',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'player' => ['id', 'name', 'trophies', 'gok_tasi', 'village' => ['id', 'name', 'world']],
            ])
            ->assertJsonPath('player.name', 'BilgeKağan');

        $player = Player::where('name', 'BilgeKağan')->first();
        $this->assertNotNull($player);
        $this->assertNotNull($player->village, 'Yeni oyuncunun obası olmalı');
        $this->assertGreaterThan(0, $player->village->buildings()->count(), 'Başlangıç binaları kurulmalı');
        $this->assertSame(config('game.gok_tasi.start'), (int) $player->gok_tasi);
    }

    public function test_ayni_oyuncu_adi_iki_kez_alinamaz(): void
    {
        $this->makePlayer('Tekrar');

        $this->postJson('/api/v1/kayit', [
            'email'       => 'baska@test.tr',
            'password'    => 'cokgizlisifre',
            'player_name' => 'Tekrar',
        ])->assertStatus(422)->assertJsonValidationErrors('player_name');
    }

    public function test_hatali_sifre_ile_giris_reddedilir(): void
    {
        $player = $this->makePlayer('GirisDeneme');

        $this->postJson('/api/v1/giris', [
            'email'    => $player->user->email,
            'password' => 'yanlissifre',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_dogru_sifre_ile_giris_belirtec_dondurur(): void
    {
        $player = $this->makePlayer('GirisBasarili');

        $this->postJson('/api/v1/giris', [
            'email'    => $player->user->email,
            'password' => 'sifre1234',
        ])->assertOk()->assertJsonStructure(['token', 'player' => ['id', 'name']]);
    }

    public function test_konuk_giris_ayni_cihaz_icin_ayni_oyuncuyu_dondurur(): void
    {
        $first = $this->postJson('/api/v1/konuk', ['device' => 'cihaz-abc-123456'])->assertCreated();
        $playerId = $first->json('player.id');

        $second = $this->postJson('/api/v1/konuk', ['device' => 'cihaz-abc-123456'])->assertOk();

        $this->assertSame($playerId, $second->json('player.id'));
    }

    public function test_belirtec_olmadan_oba_goruntulenemez(): void
    {
        $this->getJson('/api/v1/oba')->assertUnauthorized();
    }

    public function test_belirtec_ile_oba_goruntulenir(): void
    {
        $player = $this->makePlayer('ObaSahibi');

        $this->getJson('/api/v1/oba', $this->tokenFor($player))
            ->assertOk()
            ->assertJsonStructure([
                'player' => ['id', 'name', 'league'],
                'village' => ['id', 'resources', 'production', 'capacity', 'builders'],
                'buildings', 'army' => ['usage', 'troops'], 'queues',
            ]);
    }
}
