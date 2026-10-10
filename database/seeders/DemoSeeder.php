<?php

namespace Database\Seeders;

use App\Models\Clan;
use App\Models\PlayerSpell;
use App\Models\PlayerTroop;
use App\Models\SpellType;
use App\Models\TroopType;
use App\Models\User;
use App\Services\ClanService;
use App\Services\LayoutGenerator;
use App\Services\ResourceService;
use App\Services\VillageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Geliştirme ve test için örnek dünya: oyuncular, boylar, kurulu obalar,
 * ordular. Unity istemcisini gerçek veriyle denemek için kullanılır.
 *
 *   php artisan game:demo --players=40 --clans=6
 */
class DemoSeeder extends Seeder
{
    /** Yirmi dört Oğuz boyunun adları — boy adları buradan seçilir. */
    public const OGUZ_BOYLARI = [
        'Kayı', 'Bayat', 'Alkaevli', 'Karaevli', 'Yazır', 'Döğer',
        'Dodurga', 'Yaparlı', 'Avşar', 'Kızık', 'Beğdili', 'Karkın',
        'Bayındır', 'Peçenek', 'Çavuldur', 'Çepni', 'Salur', 'Eymür',
        'Alayuntlu', 'Yüreğir', 'İğdir', 'Büğdüz', 'Yıva', 'Kınık',
    ];

    public const ADLAR = [
        'Alpagut', 'Bilge', 'Kültigin', 'Tonyukuk', 'Bumin', 'İstemi',
        'Mete', 'Atilla', 'Çağrı', 'Tuğrul', 'Alparslan', 'Melikşah',
        'Bamsı', 'Deli Dumrul', 'Salur Kazan', 'Boğaç', 'Kanturalı',
        'Tomris', 'Altınay', 'Gökçen', 'Yıldıztekin', 'Konralp', 'Baybars',
        'Aybüke', 'Gülbahar', 'Sarıgül', 'Akkız', 'Çiçek', 'Börteçine',
        'Sancar', 'Üçok', 'Bozok', 'Ertuğrul', 'Osman', 'Orhan', 'Timur',
    ];

    public function __construct(
        private readonly VillageService $villages,
        private readonly LayoutGenerator $layout,
        private readonly ClanService $clans,
        private readonly ResourceService $resources,
    ) {}

    public function run(int $playerCount = 40, int $clanCount = 6): void
    {
        $players = [];

        for ($i = 0; $i < $playerCount; $i++) {
            $name = self::ADLAR[$i % count(self::ADLAR)];
            $suffix = $i >= count(self::ADLAR) ? (string) intdiv($i, count(self::ADLAR)) : '';
            $playerName = Str::of($name.$suffix)->replace(' ', '')->toString();

            $email = Str::lower(Str::ascii($playerName)).'@klanlarvadisi.test';
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::create([
                'name'     => $playerName,
                'email'    => $email,
                'password' => 'sifre1234',
            ]);

            $player = $this->villages->createForUser($user, $playerName);

            // Otağ seviyesini kademeli dağıt: 3..11
            $otag = 3 + ($i % 9);
            $village = $player->village;
            $this->layout->build($village, $otag);

            // Kaynakları depo kapasitesinin %60'ına doldur
            $caps = $this->resources->storageCaps($village->refresh());
            $village->update([
                'gold'                 => (int) ($caps['gold'] * 0.6),
                'grain'                => (int) ($caps['grain'] * 0.6),
                'iron'                 => (int) ($caps['iron'] * 0.5),
                'kut'                  => (int) ($caps['kut'] * 0.4),
                'resources_updated_at' => now(),
                'builders'             => min(5, 2 + intdiv($otag, 4)),
            ]);

            $player->update([
                'trophies'      => max(0, 200 * $otag + random_int(-150, 150)),
                'best_trophies' => 200 * $otag + 200,
                'level'         => max(1, $otag * 3),
                'xp'            => 1000 * $otag,
                'guard_until'   => null,
                'shield_until'  => null,
            ]);

            $this->giveArmy($player, $otag);
            $players[] = $player->refresh();
        }

        // Boyları kur ve oyuncuları dağıt
        $pool = collect($players)->shuffle();
        for ($c = 0; $c < $clanCount && $pool->isNotEmpty(); $c++) {
            $leader = $pool->shift();
            if (! $leader) {
                break;
            }

            $boy = self::OGUZ_BOYLARI[$c % count(self::OGUZ_BOYLARI)];
            if (Clan::where('name', $boy.' Boyu')->exists()) {
                continue;
            }

            $clan = $this->clans->create($leader, [
                'name'              => $boy.' Boyu',
                'description'       => "{$boy} boyu, Ötüken'den gelen soyunu sürdürür. Tuğumuz yüksek, atımız yorulmaz.",
                'type'              => $c % 3 === 0 ? 'acik' : 'davet',
                'required_trophies' => $c * 150,
            ]);

            $take = min($pool->count(), random_int(3, 8));
            for ($m = 0; $m < $take; $m++) {
                $member = $pool->shift();
                if ($member) {
                    $this->clans->addMember($member, $clan->refresh());
                }
            }
        }
    }

    private function giveArmy($player, int $otag): void
    {
        $pool = [
            'ceri'         => 30 + $otag * 4,
            'okcu_kiz'     => 20 + $otag * 3,
            'gurzcu'       => max(0, $otag - 2) * 2,
            'boru'         => max(0, $otag - 3) * 3,
            'tepegoz'      => max(0, $otag - 4) * 2,
            'alp'          => max(0, $otag - 6) * 2,
            'togrul'       => max(0, $otag - 4) * 4,
            'konrul'       => max(0, $otag - 9),
            'karakoncolos' => max(0, $otag - 7),
            'albasti'      => max(0, $otag - 6) * 2,
        ];

        foreach ($pool as $key => $count) {
            if ($count <= 0) {
                continue;
            }
            $type = TroopType::where('key', $key)->first();
            if (! $type) {
                continue;
            }

            PlayerTroop::updateOrCreate(
                ['player_id' => $player->id, 'troop_type_id' => $type->id],
                ['level' => max(1, min($type->max_level, intdiv($otag, 2))), 'ready' => $count]
            );
        }

        if ($otag >= 5) {
            foreach (['yildirim' => 2, 'sifa' => 2, 'gazap' => 1] as $key => $count) {
                $type = SpellType::where('key', $key)->first();
                if (! $type) {
                    continue;
                }
                PlayerSpell::updateOrCreate(
                    ['player_id' => $player->id, 'spell_type_id' => $type->id],
                    ['level' => max(1, min($type->max_level, intdiv($otag, 3))), 'ready' => $count]
                );
            }
        }
    }
}
