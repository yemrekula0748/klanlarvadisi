<?php

/*
|--------------------------------------------------------------------------
| Kahramanlar — Türk Mitolojisi ve Destanları
|--------------------------------------------------------------------------
|
| Kahramanlar Kurgan'da uyandırılır, Kut ile yükseltilir ve savaş sonunda
| yenilenme süresine girer. Her kahramanın canı %50'nin altına inince
| yeteneği kendiliğinden tetiklenir.
|
*/

return [
    [
        'key' => 'oguz_kagan', 'name' => 'Oğuz Kağan', 'title' => 'Altı Oğullu Kağan',
        'unlock_otag' => 7, 'max_level' => 50, 'regen_minutes' => 20, 'sort' => 1,
        'lore' => 'Doğduğunda bir kere süt emdi, bir daha emmedi; kırk günde '
            .'yürüdü, atına bindi. Altı oğlu altı yöne yayıldı, yirmi dört boy '
            .'ondan doğdu. Tuğunu kaldırdığında bozkır tek yürek olur.',
        'hp' => 500, 'hp_growth' => 1.055, 'dps' => 100, 'dps_growth' => 1.05,
        'speed' => 20, 'range' => 0.8, 'attack_speed' => 0.8, 'pref' => 'yok',
        'ability' => [
            'key' => 'bozkurt_cagrisi', 'name' => 'Bozkurt Çağrısı',
            'description' => 'Oğuz Kağan tuğunu kaldırır: çevresindeki askerler '
                .'hızlanır, kendisi iyileşir ve yanında börüler belirir.',
            'heal_base' => 600, 'heal_growth' => 1.05,
            'radius' => 6.0, 'duration' => 8, 'speed_bonus' => 0.6, 'damage_bonus' => 0.35,
            'summon' => 'boru', 'summon_count' => 4,
            'summon_hp_base' => 400, 'summon_dps_base' => 60,
        ],
    ],
    [
        'key' => 'asena', 'name' => 'Asena', 'title' => 'Gök Kurdun Anası',
        'unlock_otag' => 9, 'max_level' => 50, 'regen_minutes' => 30, 'sort' => 2,
        'lore' => 'Yaralı çocuğu büyüten dişi kurt. Ergenekon\'da soyu o '
            .'sürdürdü. Ok gibi hızlıdır; uzaktan vurur, yakınına kimseyi '
            .'bırakmaz. Ululaması duyulduğunda düşman atları şaha kalkar.',
        'hp' => 420, 'hp_growth' => 1.055, 'dps' => 135, 'dps_growth' => 1.052,
        'speed' => 24, 'range' => 5.0, 'attack_speed' => 0.6, 'pref' => 'yok',
        'ability' => [
            'key' => 'gok_ulumasi', 'name' => 'Gök Uluması',
            'description' => 'Asena göğe ulur: görünmez olur, hasarı ve hızı '
                .'artar, yakınındaki kurtlar ona katılır.',
            'heal_base' => 350, 'heal_growth' => 1.05,
            'radius' => 5.0, 'duration' => 7, 'speed_bonus' => 0.8, 'damage_bonus' => 0.7,
            'summon' => 'boru', 'summon_count' => 3,
            'summon_hp_base' => 350, 'summon_dps_base' => 70,
        ],
    ],
    [
        'key' => 'korkut_ata', 'name' => 'Korkut Ata', 'title' => 'Kopuzun Kamı',
        'unlock_otag' => 11, 'max_level' => 40, 'regen_minutes' => 30, 'sort' => 3,
        'lore' => 'Kopuzunu çalınca sular durulur, yaralar kapanır. Ad veren, '
            .'öğüt veren, destan söyleyen bilge kam. Savaşta kılıç kuşanmaz; '
            .'ama onun olduğu safta kimse kolay ölmez.',
        'hp' => 700, 'hp_growth' => 1.05, 'dps' => 40, 'dps_growth' => 1.04,
        'speed' => 18, 'range' => 4.0, 'attack_speed' => 1.2, 'pref' => 'yok',
        'ability' => [
            'key' => 'kopuz_tilsimi', 'name' => 'Kopuz Tılsımı',
            'description' => 'Korkut Ata kopuzunu çalar: çevresindeki bütün '
                .'askerler bir süre ölümsüz gibi dayanır ve iyileşir.',
            'heal_base' => 1200, 'heal_growth' => 1.05,
            'radius' => 7.0, 'duration' => 9, 'heal_per_second' => 120,
            'speed_bonus' => 0.2, 'damage_bonus' => 0.2,
        ],
    ],
    [
        'key' => 'tomris_hatun', 'name' => 'Tomris Hatun', 'title' => 'Saka Kağanı',
        'unlock_otag' => 13, 'max_level' => 35, 'regen_minutes' => 40, 'sort' => 4,
        'lore' => 'Oğlunun kanını isteyen kağan. "Kana doymadın mı?" diyip '
            .'düşman hükümdarının başını tuluma soktuğu anlatılır. '
            .'Gürzü düştüğü yerde taş kırılır, duvar yarılır.',
        'hp' => 900, 'hp_growth' => 1.05, 'dps' => 180, 'dps_growth' => 1.05,
        'speed' => 20, 'range' => 1.0, 'attack_speed' => 1.0, 'pref' => 'savunma',
        'ability' => [
            'key' => 'kan_yemini', 'name' => 'Kan Yemini',
            'description' => 'Tomris Hatun yemin eder: gürzü yere vurur, '
                .'çevredeki yapılara ağır hasar verir ve kendi hasarı katlanır.',
            'heal_base' => 500, 'heal_growth' => 1.05,
            'radius' => 5.5, 'duration' => 8, 'speed_bonus' => 0.3, 'damage_bonus' => 1.2,
        ],
    ],
];
