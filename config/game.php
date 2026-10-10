<?php

/*
|--------------------------------------------------------------------------
| Klanlar Vadisi — Oyun Dengesi (Game Balance)
|--------------------------------------------------------------------------
|
| Tüm oyun sabitleri burada toplanmıştır. Sunucu, saldırı simülasyonu ve
| üretim hesapları bu değerleri kullanır. Değerleri değiştirmek için
| migration gerekmez; `php artisan game:sync` ile katalog yenilenir.
|
*/

return [

    'version' => '1.0.0',

    /*
    | Dünya haritası — istemci ve sunucu aynı tohumla aynı araziyi üretir.
    */
    'world' => [
        'seed'        => (int) env('GAME_WORLD_SEED', 20261071),
        'width'       => (int) env('GAME_WORLD_WIDTH', 1600),
        'height'      => (int) env('GAME_WORLD_HEIGHT', 1600),
        'chunk'       => 32,       // tek seferde çekilen kare alan kenarı
        'max_chunk_tiles' => 4096, // API başına üst sınır
        'spawn_ring'  => 64,       // yeni oyuncular merkezden kaç kare uzağa düşer
        'village_min_distance' => 3,
    ],

    /*
    | Köy (Oba) ızgarası — savaş simülasyonu bu ızgarada çalışır.
    */
    'grid' => [
        'size'    => 44,  // 44 x 44
        'margin'  => 3,   // kenarda bina kurulamayan şerit (asker iniş alanı)
    ],

    /*
    | Kaynaklar
    */
    'resources' => ['gold', 'grain', 'iron', 'kut'],

    'resource_labels' => [
        'gold'  => 'Altın',
        'grain' => 'Tahıl',
        'iron'  => 'Demir',
        'kut'   => 'Kut',
    ],

    /*
    | Küresel denge çarpanları
    |
    | Katalog üretilirken uygulanır (`php artisan db:seed --class=CatalogSeeder`).
    | Tek bir sayıyı değiştirip `php artisan game:balance` ile ölçün: bütün
    | tabloları elle düzenlemeye gerek yoktur.
    |
    | 1.00 = ham tablo değeri. >1 güçlendirir, <1 zayıflatır.
    */
    'tuning' => [
        'troop_hp'    => (float) env('GAME_TUNE_TROOP_HP', 1.18),
        'troop_dps'   => (float) env('GAME_TUNE_TROOP_DPS', 1.18),
        'hero_hp'     => (float) env('GAME_TUNE_HERO_HP', 1.0),
        'hero_dps'    => (float) env('GAME_TUNE_HERO_DPS', 1.0),
        'defense_hp'  => (float) env('GAME_TUNE_DEFENSE_HP', 1.0),
        'defense_dps' => (float) env('GAME_TUNE_DEFENSE_DPS', 1.0),
        'wall_hp'     => (float) env('GAME_TUNE_WALL_HP', 0.92),
    ],

    /*
    | Savaş simülasyonu
    */
    'battle' => [
        'tick_rate'        => 10,    // saniyedeki adım sayısı
        'max_duration'     => 180,   // saniye
        'preparation'      => 30,    // asker indirme süresi (saniye)
        'wall_path_cost'   => 18,    // duvar hücresinin yol maliyeti (yüksek => etrafından dolaşır)
        'star_destruction' => 50,    // 1. yıldız için gereken yıkım yüzdesi
        'loot_percent' => [
            // Otağ seviye farkı => yağmalanabilir kaynak yüzdesi
            'base'    => 0.20,
            'min'     => 0.05,
            'per_level_penalty' => 0.045,
        ],
        'shield_hours' => [
            1 => 12,  // 1 yıldız yiyen oyuncu
            2 => 14,
            3 => 16,
        ],
        'guard_hours' => 1,
        'revenge_window_hours' => 24,
    ],

    /*
    | Kupa (trophy) hesabı — Elo benzeri
    */
    'trophies' => [
        'start'      => 0,
        'k_factor'   => 30,
        'min_gain'   => 4,
        'max_gain'   => 60,
        'league_tiers' => [
            ['key' => 'cakmaktas',  'name' => 'Çakmaktaşı Boyu', 'min' => 0,    'color' => '#8d8375'],
            ['key' => 'bakir',      'name' => 'Bakır Tuğ',       'min' => 400,  'color' => '#b06a3b'],
            ['key' => 'demir',      'name' => 'Demir Tuğ',       'min' => 800,  'color' => '#7a8694'],
            ['key' => 'gumus',      'name' => 'Gümüş Tuğ',       'min' => 1200, 'color' => '#b9c4d0'],
            ['key' => 'altin',      'name' => 'Altın Tuğ',       'min' => 1700, 'color' => '#e0a63c'],
            ['key' => 'kurt',       'name' => 'Kurt Başı',       'min' => 2300, 'color' => '#6f9bd1'],
            ['key' => 'konrul',     'name' => 'Konrul Kanadı',   'min' => 3000, 'color' => '#9b6fd1'],
            ['key' => 'gok',        'name' => 'Gök Tengri',      'min' => 3800, 'color' => '#38bdf8'],
            ['key' => 'ergenekon',  'name' => 'Ergenekon',       'min' => 4600, 'color' => '#f05a4f'],
            ['key' => 'kagan',      'name' => 'Kağanlar Katı',   'min' => 5400, 'color' => '#ffd166'],
        ],
    ],

    /*
    | Klan (Boy) ayarları
    */
    'clan' => [
        'member_limit'       => 50,
        'name_min'           => 3,
        'name_max'           => 24,
        'tag_length'         => 6,
        'donation_cooldown'  => 0,
        'war_sizes'          => [5, 10, 15, 20, 25, 30, 40, 50],
        'war_preparation_hours' => 23,
        'war_battle_hours'      => 24,
        'war_attacks_per_member' => 2,
        'xp_per_war_win'     => 50,
        'levels'             => [0, 1000, 3000, 7000, 14000, 26000, 44000, 70000, 110000, 170000],
    ],

    /*
    | Gök Taşı (premium) ile hızlandırma
    */
    'gok_tasi' => [
        'start'          => 500,
        'instant_tiers'  => [
            ['max_seconds' => 60,      'cost' => 1],
            ['max_seconds' => 3600,    'cost' => 20],
            ['max_seconds' => 86400,   'cost' => 260],
            ['max_seconds' => 604800,  'cost' => 1000],
            ['max_seconds' => PHP_INT_MAX, 'cost' => 2800],
        ],
    ],

    /*
    | Oyuncu seviyesi için gereken tecrübe (XP)
    */
    'xp_curve' => [
        0, 50, 150, 300, 600, 1000, 1600, 2400, 3500, 5000,
        7000, 9500, 12500, 16000, 20000, 25000, 31000, 38000, 46000, 55000,
        66000, 79000, 94000, 111000, 130000, 152000, 177000, 205000, 236000, 270000,
    ],

    /*
    | NPC / mitolojik kamp saldırıları (tek oyunculu görevler)
    */
    'npc' => [
        'reward_multiplier' => 1.0,
    ],
];
