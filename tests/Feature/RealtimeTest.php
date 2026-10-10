<?php

namespace Tests\Feature;

use App\Events\ClanChatMessage;
use App\Events\VillageAttacked;
use App\Events\WarStateChanged;
use App\Services\ClanService;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\GameTestCase;

/**
 * Gerçek zamanlı katman: Unity istemcisi Reverb'e bağlanıp özel kanallara
 * katılırken `/broadcasting/auth` ucunu Sanctum belirteciyle çağırır. Bu
 * testler o yetkilendirmenin doğru çalıştığını ve olay adlarının istemcinin
 * beklediği adlarla eşleştiğini güvenceye alır.
 */
class RealtimeTest extends GameTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Testlerde varsayılan yayıncı `null`; o sürücünün `auth()` gövdesi
        // boştur, yani kanal yetkisini hiç denetlemez. Üretimde Reverb
        // (Pusher protokolü) kullanıldığı için burada da Pusher sürücüsünü
        // sahte kimlik bilgileriyle kuruyoruz — imza yerel HMAC ile
        // hesaplandığından ağ erişimi gerekmez.
        config([
            'broadcasting.default'                   => 'pusher',
            'broadcasting.connections.pusher.key'     => 'test-anahtar',
            'broadcasting.connections.pusher.secret'  => 'test-gizli',
            'broadcasting.connections.pusher.app_id'  => 'test-uygulama',
        ]);

        // Kanallar uygulama açılışında o anki sürücüye kaydedilir; sürücüyü
        // sonradan değiştirdiğimiz için yeniden kaydetmek gerekir.
        require base_path('routes/channels.php');
    }

    public function test_oyuncu_kendi_kisisel_kanalina_katilabilir(): void
    {
        $player = $this->makePlayer('Yetki Sahibi');

        $this->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-oyuncu.'.$player->id,
        ], $this->tokenFor($player))->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_baskasinin_kisisel_kanalina_katilamaz(): void
    {
        $player   = $this->makePlayer('Meraklı');
        $yabanci  = $this->makePlayer('Yabancı');

        $this->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-oyuncu.'.$yabanci->id,
        ], $this->tokenFor($player))->assertForbidden();
    }

    public function test_belirtecsiz_istek_reddedilir(): void
    {
        $player = $this->makePlayer('Kimliksiz');

        $this->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-oyuncu.'.$player->id,
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_boy_kanalina_yalnizca_uyeler_katilir(): void
    {
        $lider  = $this->makePlayer('Boy Beyi');
        $uye    = $this->makePlayer('Boy Üyesi');
        $disi   = $this->makePlayer('Boydan Değil');

        $clan = app(ClanService::class)->create($lider, ['name' => 'Bayat Boyu', 'type' => 'acik']);
        app(ClanService::class)->addMember($uye, $clan);

        $kanal = 'private-boy.'.$clan->id;

        $this->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => $kanal,
        ], $this->tokenFor($uye->refresh()))->assertOk();

        $this->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => $kanal,
        ], $this->tokenFor($disi))->assertForbidden();
    }

    public function test_olay_adlari_ve_kanallari_istemciyle_ayni(): void
    {
        $this->assertSame('oba.saldirildi', (new \ReflectionClass(VillageAttacked::class))
            ->newInstanceWithoutConstructor()->broadcastAs());
        $this->assertSame('boy.sohbet', (new \ReflectionClass(ClanChatMessage::class))
            ->newInstanceWithoutConstructor()->broadcastAs());
        $this->assertSame('savas.guncelleme', (new \ReflectionClass(WarStateChanged::class))
            ->newInstanceWithoutConstructor()->broadcastAs());
    }

    public function test_saldiri_olayi_savunanin_kanalinda_yayinlanir(): void
    {
        $savunan = $this->makePlayer('Savunan');

        $battle = new \App\Models\Battle();
        $battle->defender_player_id = $savunan->id;

        $kanallar = (new VillageAttacked($battle))->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $kanallar[0]);
        $this->assertSame('private-oyuncu.'.$savunan->id, $kanallar[0]->name);
    }
}
