<?php

namespace Tests;

use App\Models\Player;
use App\Models\PlayerTroop;
use App\Models\TroopType;
use App\Models\User;
use App\Services\LayoutGenerator;
use App\Services\VillageService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Oyun testleri için ortak temel: katalog bir kez tohumlanır, oyuncu
 * oluşturma ve ordu verme kısayolları sağlanır.
 */
abstract class GameTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    /** Oyuncu + oba oluşturur, istenirse hazır düzen kurar. */
    protected function makePlayer(string $name = null, int $otagLevel = 1): Player
    {
        $name = $name ?: 'Oyuncu'.random_int(100000, 999999);

        $user = User::create([
            'name'     => $name,
            'email'    => strtolower($name).'@test.local',
            'password' => 'sifre1234',
        ]);

        $player = app(VillageService::class)->createForUser($user, $name);

        if ($otagLevel > 1) {
            app(LayoutGenerator::class)->build($player->village, $otagLevel);
            $player->village->refresh();
        }

        return $player->refresh();
    }

    /** Oyuncuya asker verir (eğitim kuyruğunu atlayarak). */
    protected function giveTroops(Player $player, array $troops, int $level = 1): void
    {
        foreach ($troops as $key => $count) {
            $type = TroopType::where('key', $key)->firstOrFail();
            PlayerTroop::updateOrCreate(
                ['player_id' => $player->id, 'troop_type_id' => $type->id],
                ['level' => min($level, $type->max_level), 'ready' => $count]
            );
        }
    }

    /**
     * Obaya kaynak yükler. Varsayılan olarak depo kapasitesini doldurur;
     * `$amount` verilirse kapasiteyle sınırlanır (gerçekçi durum).
     */
    protected function giveResources(Player $player, ?int $amount = null): void
    {
        $caps = app(\App\Services\ResourceService::class)->storageCaps($player->village);

        $player->village->update([
            'gold'                 => $amount ? min($amount, $caps['gold']) : $caps['gold'],
            'grain'                => $amount ? min($amount, $caps['grain']) : $caps['grain'],
            'iron'                 => $amount ? min($amount, $caps['iron']) : $caps['iron'],
            'kut'                  => $amount ? min($amount, $caps['kut']) : $caps['kut'],
            'resources_updated_at' => now(),
        ]);
    }

    /** Depo sınırını aşan miktar gerektiğinde (test senaryoları için). */
    protected function forceResources(Player $player, int $amount): void
    {
        $player->village->update([
            'gold' => $amount, 'grain' => $amount, 'iron' => $amount, 'kut' => $amount,
            'resources_updated_at' => now(),
        ]);
    }

    /**
     * Sanctum belirteci ile istek atmak için başlık.
     *
     * NOT: Laravel testlerinde kimlik doğrulama guard'ı, çözümlediği
     * kullanıcıyı uygulama ömrü boyunca önbellekler. Aynı test içinde farklı
     * oyuncularla istek atabilmek için guard'ları burada sıfırlıyoruz.
     */
    protected function tokenFor(Player $player): array
    {
        $this->app['auth']->forgetGuards();

        $token = $this->tokens[$player->id] ??= $player->user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    /** @var array<int,string> */
    private array $tokens = [];
}
