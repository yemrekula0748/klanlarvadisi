<?php

/*
|--------------------------------------------------------------------------
| Tılsımlar (Büyüler)
|--------------------------------------------------------------------------
|
| Büyü Atölyesi'nde Kut ve demir ile dökülür. `effect` alanı simülatöre
| doğrudan geçer: damage (anlık hasar), duration (süreli alan etkisi),
| heal_per_second, speed_bonus, damage_bonus, freeze, summon.
|
*/

return [
    [
        'key' => 'yildirim', 'name' => 'Yıldırım Tılsımı', 'category' => 'ak',
        'housing' => 2, 'brew' => 180, 'cost' => ['kut' => 90],
        'max_level' => 8, 'radius' => 3.0, 'unlock' => 1, 'sort' => 1,
        'description' => 'Belirlediğin yere gök gürültüsüyle yıldırım indirir.',
        'lore' => 'Yada taşı suya değer, kam ellerini kaldırır; gök yarılır. '
            .'Yıldırımın düştüğü yere kırk yıl ot bitmez derler.',
        'effect' => ['damage_base' => 150, 'damage_growth' => 1.22],
    ],
    [
        'key' => 'sifa', 'name' => 'Şifa Tılsımı', 'category' => 'ak',
        'housing' => 2, 'brew' => 180, 'cost' => ['kut' => 110],
        'max_level' => 8, 'radius' => 5.0, 'unlock' => 2, 'sort' => 2,
        'description' => 'Alanı içindeki askerleri sürekli iyileştirir.',
        'lore' => 'Umay Ana\'nın eli değdiği yerde kan durur. Ardıç dumanı '
            .'yükselirken yaralılar ayağa kalkar.',
        'effect' => ['duration' => 12, 'heal_base' => 40, 'heal_growth' => 1.18],
    ],
    [
        'key' => 'gazap', 'name' => 'Gazap Tılsımı', 'category' => 'ak',
        'housing' => 2, 'brew' => 180, 'cost' => ['kut' => 130],
        'max_level' => 6, 'radius' => 4.5, 'unlock' => 3, 'sort' => 3,
        'description' => 'Askerleri hızlandırır ve hasarlarını artırır.',
        'lore' => 'Savaş davulu hızlanır, kan tepeye çıkar. Gazaba gelen er '
            .'ne yorgunluk bilir ne korku.',
        'effect' => ['duration' => 10, 'speed_bonus_base' => 0.55, 'damage_bonus_base' => 0.4, 'bonus_growth' => 1.08],
    ],
    [
        'key' => 'karakis', 'name' => 'Karakış Tılsımı', 'category' => 'ak',
        'housing' => 1, 'brew' => 120, 'cost' => ['kut' => 90],
        'max_level' => 7, 'radius' => 3.5, 'unlock' => 5, 'sort' => 4,
        'description' => 'Alandaki savunma yapılarını bir süre dondurur.',
        'lore' => 'Karakoncolos\'un soluğu şişeye hapsedilmiştir. Şişe '
            .'kırıldığında kuleler buz tutar, okçunun parmakları donar.',
        'effect' => ['duration' => 4, 'duration_growth' => 1.12, 'freeze' => true],
    ],
    [
        'key' => 'yarma', 'name' => 'Yarma Tılsımı', 'category' => 'ak',
        'housing' => 2, 'brew' => 180, 'cost' => ['kut' => 100],
        'max_level' => 4, 'radius' => 3.0, 'unlock' => 6, 'sort' => 5,
        'description' => 'Alandaki surlara yıkıcı hasar verir, geçit açar.',
        'lore' => 'Demirci Ata\'nın körüğünden bir soluk... Taş erir, '
            .'duvar yarılır, yol açılır.',
        'effect' => ['damage_base' => 1400, 'damage_growth' => 1.3],
    ],
    [
        'key' => 'cagri', 'name' => 'Çağrı Tılsımı', 'category' => 'kara',
        'housing' => 1, 'brew' => 150, 'cost' => ['kut' => 70, 'iron' => 30],
        'max_level' => 6, 'radius' => 2.0, 'unlock' => 4, 'sort' => 6,
        'description' => 'Belirlediğin yere börü sürüsü çağırır.',
        'lore' => 'Kam kurt derisini sırtına alır, üç kere ulur. '
            .'Bozkırın dört bir yanından kurtlar koşarak gelir.',
        'effect' => [
            'summon' => 'boru', 'summon_count_base' => 4, 'summon_count_growth' => 1,
            'summon_hp_base' => 320, 'summon_dps_base' => 55, 'summon_growth' => 1.18,
        ],
    ],
    [
        'key' => 'zehir', 'name' => 'Zehir Tılsımı', 'category' => 'kara',
        'housing' => 1, 'brew' => 150, 'cost' => ['kut' => 60, 'iron' => 40],
        'max_level' => 6, 'radius' => 3.5, 'unlock' => 6, 'sort' => 7,
        'description' => 'Alandaki savunan askerleri zehirler ve yavaşlatır.',
        'lore' => 'Şahmeran\'ın bir damla zehri, bir testi suyu kara eder. '
            .'Kam onu balmumuna döküp mühürler.',
        'effect' => ['duration' => 8, 'damage_base' => 60, 'damage_growth' => 1.2],
    ],
    [
        'key' => 'golge', 'name' => 'Gölge Tılsımı', 'category' => 'kara',
        'housing' => 1, 'brew' => 120, 'cost' => ['kut' => 50, 'iron' => 50],
        'max_level' => 5, 'radius' => 4.0, 'unlock' => 8, 'sort' => 8,
        'description' => 'Askerleri gölgeye büründürür: çok hızlanır, görünmez gibi ilerler.',
        'lore' => 'Erlik\'in karanlığından bir parça. Gölgeye giren er, '
            .'okçunun gözünden kaçar — ama kendi gölgesini de kaybeder.',
        'effect' => ['duration' => 8, 'speed_bonus_base' => 1.2, 'bonus_growth' => 1.1],
    ],
];
