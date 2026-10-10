<?php

/*
|--------------------------------------------------------------------------
| Bina Kataloğu — Türk Mitolojisi
|--------------------------------------------------------------------------
|
| Her bina için temel değerler ve seviye başına büyüme katsayıları verilir.
| Seviye tabloları `BuildingSeeder` tarafından bu formüllerle üretilir;
| böylece denge ayarı tek bir yerden yapılabilir.
|
| cost       : 1. seviye maliyeti, kaynak => miktar
| cost_growth: her seviyede maliyet çarpanı
| time       : 1. seviye inşa süresi (saniye)
| time_growth: her seviyede süre çarpanı
| hp / prod / storage / dps / range / capacity : 1. seviye değerleri
| *_growth   : her seviyede çarpan
| otag       : her bina seviyesi için gereken asgari Otağ seviyesi
| count      : Otağ seviyesi => izin verilen adet
|
*/

return [

    /* ----------------------------------------------------------------- */
    /* Merkez                                                             */
    /* ----------------------------------------------------------------- */
    [
        'key' => 'otag', 'name' => 'Otağ', 'category' => 'otag',
        'grid' => [4, 4], 'max_level' => 15, 'sort' => 0,
        'description' => 'Obanın kalbi. Kağanın çadırı yıkılırsa oba düşer.',
        'lore' => 'Dokuz tuğlu otağ, göğün direğinin yere değdiği yerdir. '
            .'Kağan burada oturur, boyun kutu buradan dağılır. Otağ yükseldikçe '
            .'oba büyür, yeni yapılar ve yeni erler çağrılır.',
        'count' => [1 => 1],
        'hp' => 1500, 'hp_growth' => 1.22,
        'storage' => 1500, 'storage_growth' => 1.55,
        'cost' => ['gold' => 0], 'cost_growth' => 1.0,
        'cost_table' => [
            2 => ['gold' => 1000], 3 => ['gold' => 4000], 4 => ['gold' => 25000],
            5 => ['gold' => 150000], 6 => ['gold' => 750000], 7 => ['gold' => 1200000],
            8 => ['gold' => 2000000], 9 => ['gold' => 3000000], 10 => ['gold' => 4000000],
            11 => ['gold' => 5000000], 12 => ['gold' => 6000000], 13 => ['gold' => 7000000],
            14 => ['gold' => 8000000], 15 => ['gold' => 9000000],
        ],
        'time' => 0,
        'time_table' => [
            2 => 60, 3 => 1800, 4 => 10800, 5 => 86400, 6 => 172800, 7 => 259200,
            8 => 345600, 9 => 432000, 10 => 518400, 11 => 604800, 12 => 691200,
            13 => 777600, 14 => 864000, 15 => 950400,
        ],
        'xp' => 100, 'xp_growth' => 1.4,
    ],

    /* ----------------------------------------------------------------- */
    /* Kaynak üreten yapılar                                              */
    /* ----------------------------------------------------------------- */
    [
        'key' => 'altin_madeni', 'name' => 'Altın Madeni', 'category' => 'kaynak',
        'produces' => 'gold', 'grid' => [3, 3], 'max_level' => 14, 'sort' => 10,
        'description' => 'Dağın damarından altın çıkarır.',
        'lore' => 'Altay\'ın derinliklerinde, Yer-Su iyelerinin bekçilik ettiği '
            .'damarlar vardır. Madenciler toprağa süt döküp izin ister, sonra kazmaya başlar.',
        'count' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 6 => 5, 8 => 6, 10 => 7],
        'hp' => 400, 'hp_growth' => 1.16,
        'prod' => 200, 'prod_growth' => 1.42,
        'cost' => ['gold' => 150], 'cost_growth' => 2.3,
        'time' => 60, 'time_growth' => 2.1,
        'otag' => [1 => 1, 3 => 2, 5 => 3, 7 => 4, 9 => 6, 11 => 8, 13 => 10],
        'xp' => 5, 'xp_growth' => 1.5,
    ],
    [
        'key' => 'tarla', 'name' => 'Tarla', 'category' => 'kaynak',
        'produces' => 'grain', 'grid' => [3, 3], 'max_level' => 14, 'sort' => 11,
        'description' => 'Darı ve buğday yetiştirir; asker karnını burada doyurur.',
        'lore' => 'Bozkırda tahıl kutsaldır: ilk hasat Umay Ana\'ya sunulur, '
            .'kalanı obanın ambarına girer. Tarla olmadan ordu yürümez.',
        'count' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 6 => 5, 8 => 6, 10 => 7],
        'hp' => 400, 'hp_growth' => 1.16,
        'prod' => 200, 'prod_growth' => 1.42,
        'cost' => ['grain' => 150], 'cost_growth' => 2.3,
        'time' => 60, 'time_growth' => 2.1,
        'otag' => [1 => 1, 3 => 2, 5 => 3, 7 => 4, 9 => 6, 11 => 8, 13 => 10],
        'xp' => 5, 'xp_growth' => 1.5,
    ],
    [
        'key' => 'demir_ocagi', 'name' => 'Demir Ocağı', 'category' => 'kaynak',
        'produces' => 'iron', 'grid' => [3, 3], 'max_level' => 10, 'sort' => 12,
        'description' => 'Demir eritir. Asker geliştirmenin tek yolu demirdir.',
        'lore' => 'Ergenekon\'dan çıkışın sırrı demirdedir. Demirci Ata, dağı '
            .'eritip yol açtığından beri her ocak o ateşin kıvılcımını taşır.',
        'count' => [5 => 1, 7 => 2, 9 => 3, 11 => 4],
        'hp' => 900, 'hp_growth' => 1.18,
        'prod' => 400, 'prod_growth' => 1.40,
        'cost' => ['gold' => 150000], 'cost_growth' => 1.9,
        'time' => 21600, 'time_growth' => 1.7,
        'otag' => [1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 10, 7 => 11, 8 => 12, 9 => 13, 10 => 14],
        'xp' => 150, 'xp_growth' => 1.35,
    ],
    [
        'key' => 'kam_cadiri', 'name' => 'Kam Çadırı', 'category' => 'kaynak',
        'produces' => 'kut', 'grid' => [3, 3], 'max_level' => 8, 'sort' => 13,
        'description' => 'Kam (şaman) davul çalar, göğe yükselir, Kut toplar.',
        'lore' => 'Kam dokuz katlı göğe çıkar, Ülgen\'in katında kut dileyip döner. '
            .'Kut; tılsımların, kahramanların ve kutsal işlerin enerjisidir.',
        'count' => [8 => 1, 10 => 2, 12 => 3],
        'hp' => 1400, 'hp_growth' => 1.18,
        'prod' => 60, 'prod_growth' => 1.38,
        'cost' => ['iron' => 25000], 'cost_growth' => 1.9,
        'time' => 43200, 'time_growth' => 1.6,
        'otag' => [1 => 8, 2 => 9, 3 => 10, 4 => 11, 5 => 12, 6 => 13, 7 => 14, 8 => 15],
        'xp' => 200, 'xp_growth' => 1.3,
    ],

    /* ----------------------------------------------------------------- */
    /* Depolar                                                            */
    /* ----------------------------------------------------------------- */
    [
        'key' => 'hazine', 'name' => 'Hazine', 'category' => 'depo',
        'stores' => 'gold', 'grid' => [3, 3], 'max_level' => 13, 'sort' => 20,
        'description' => 'Altını saklar. Yağmacılar ilk burayı arar.',
        'lore' => 'Kağanın hazinesi kurganın altındadır; üstüne taş baba dikilir '
            .'ki kötü ruhlar ve komşu boylar uzak dursun.',
        'count' => [1 => 1, 2 => 2, 3 => 3, 5 => 4],
        'hp' => 400, 'hp_growth' => 1.18,
        'storage' => 1000, 'storage_growth' => 1.7,
        'cost' => ['gold' => 300], 'cost_growth' => 2.4,
        'time' => 60, 'time_growth' => 2.2,
        'otag' => [1 => 1, 3 => 2, 5 => 3, 7 => 5, 9 => 7, 11 => 9, 13 => 11],
        'xp' => 6, 'xp_growth' => 1.5,
    ],
    [
        'key' => 'ambar', 'name' => 'Ambar', 'category' => 'depo',
        'stores' => 'grain', 'grid' => [3, 3], 'max_level' => 13, 'sort' => 21,
        'description' => 'Tahılı saklar.',
        'lore' => 'Kışın uzun olduğu yerde ambar, kılıçtan değerlidir. '
            .'Ambarı dolu obanın atı da, eri de güçlü olur.',
        'count' => [1 => 1, 2 => 2, 3 => 3, 5 => 4],
        'hp' => 400, 'hp_growth' => 1.18,
        'storage' => 1000, 'storage_growth' => 1.7,
        'cost' => ['grain' => 300], 'cost_growth' => 2.4,
        'time' => 60, 'time_growth' => 2.2,
        'otag' => [1 => 1, 3 => 2, 5 => 3, 7 => 5, 9 => 7, 11 => 9, 13 => 11],
        'xp' => 6, 'xp_growth' => 1.5,
    ],
    [
        'key' => 'demir_deposu', 'name' => 'Demir Deposu', 'category' => 'depo',
        'stores' => 'iron', 'grid' => [3, 3], 'max_level' => 9, 'sort' => 22,
        'description' => 'Ham demiri saklar.',
        'lore' => 'Demir, nemden ve nazardan korunur. Deponun kapısına demirci '
            .'çekici asılır — hem bereket, hem bekçilik için.',
        'count' => [5 => 1, 8 => 2],
        'hp' => 1000, 'hp_growth' => 1.2,
        'storage' => 20000, 'storage_growth' => 1.65,
        'cost' => ['gold' => 300000], 'cost_growth' => 1.85,
        'time' => 28800, 'time_growth' => 1.6,
        'otag' => [1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 10, 7 => 11, 8 => 12, 9 => 13],
        'xp' => 170, 'xp_growth' => 1.3,
    ],
    [
        'key' => 'kut_tasi', 'name' => 'Kut Taşı', 'category' => 'depo',
        'stores' => 'kut', 'grid' => [2, 2], 'max_level' => 6, 'sort' => 23,
        'description' => 'Göktaşından yontulmuş dikilitaş; Kut biriktirir.',
        'lore' => 'Göğden düşen taş, üzerine damgalar kazınınca kut tutar. '
            .'Kam onun gölgesinde oturup geleceği okur.',
        'count' => [8 => 1, 11 => 2],
        'hp' => 1600, 'hp_growth' => 1.2,
        'storage' => 1000, 'storage_growth' => 1.7,
        'cost' => ['iron' => 70000], 'cost_growth' => 1.9,
        'time' => 43200, 'time_growth' => 1.55,
        'otag' => [1 => 8, 2 => 9, 3 => 10, 4 => 11, 5 => 12, 6 => 13],
        'xp' => 200, 'xp_growth' => 1.3,
    ],

    /* ----------------------------------------------------------------- */
    /* Ordu yapıları                                                      */
    /* ----------------------------------------------------------------- */
    [
        'key' => 'kisla', 'name' => 'Kışla', 'category' => 'ordu',
        'grid' => [3, 3], 'max_level' => 13, 'sort' => 30,
        'description' => 'Er yetiştirir. Seviyesi yeni asker türlerini açar.',
        'lore' => 'Kışlada ok atmak, kılıç savurmak ve at binmek öğretilir. '
            .'Usta çavuşlar "bir er, bir ordudur" der.',
        'count' => [1 => 1, 2 => 2, 4 => 3, 6 => 4],
        'hp' => 250, 'hp_growth' => 1.2,
        'cost' => ['grain' => 200], 'cost_growth' => 2.35,
        'time' => 60, 'time_growth' => 2.15,
        'otag' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7,
            8 => 8, 9 => 9, 10 => 10, 11 => 11, 12 => 12, 13 => 13],
        'xp' => 10, 'xp_growth' => 1.5,
    ],
    [
        'key' => 'kara_kisla', 'name' => 'Kara Kışla', 'category' => 'ordu',
        'grid' => [3, 3], 'max_level' => 9, 'sort' => 31,
        'description' => 'Karanlık varlıkları çağırır: Albastı, Karakoncolos, Erlik çömezleri.',
        'lore' => 'Erlik\'in yurduna açılan kuyunun başında kurulur. İçeri giren '
            .'kam, adını geride bırakır.',
        'count' => [7 => 1, 9 => 2],
        'hp' => 1000, 'hp_growth' => 1.2,
        'cost' => ['iron' => 25000], 'cost_growth' => 1.95,
        'time' => 14400, 'time_growth' => 1.7,
        'otag' => [1 => 7, 2 => 8, 3 => 9, 4 => 10, 5 => 11, 6 => 12, 7 => 13, 8 => 14, 9 => 15],
        'xp' => 120, 'xp_growth' => 1.35,
    ],
    [
        'key' => 'ordugah', 'name' => 'Ordugâh', 'category' => 'ordu',
        'grid' => [4, 4], 'max_level' => 9, 'sort' => 32,
        'description' => 'Askerin konakladığı yer. Ordu kapasitesini belirler.',
        'lore' => 'Ordugâh kurulurken çadırlar ay biçiminde dizilir, ortaya tuğ '
            .'dikilir. Tuğ düşmedikçe ordu dağılmaz.',
        'count' => [1 => 1, 2 => 2, 4 => 3, 8 => 4],
        'hp' => 250, 'hp_growth' => 1.2,
        // Ordugâh başına asker alanı. Toplam kapasite eğrisi:
        // Otağ 3 -> 70, Otağ 5 -> 135, Otağ 9 -> 220, Otağ 11 -> 260, Otağ 13 -> 280.
        'capacity_table' => [20, 35, 45, 50, 55, 60, 65, 70, 75],
        'cost' => ['grain' => 250], 'cost_growth' => 2.4,
        'time' => 30, 'time_growth' => 2.3,
        'otag' => [1 => 1, 2 => 2, 3 => 4, 4 => 6, 5 => 7, 6 => 9, 7 => 10, 8 => 12, 9 => 13],
        'xp' => 8, 'xp_growth' => 1.5,
    ],
    [
        'key' => 'demirci', 'name' => 'Demirci', 'category' => 'ordu',
        'grid' => [3, 3], 'max_level' => 12, 'sort' => 33,
        'description' => 'Asker ve tılsım seviyelerini yükseltir.',
        'lore' => 'Örsün sesi obanın nabzıdır. Demirci Ata\'nın çırakları, '
            .'kılıca su verirken eski ağıtları söyler.',
        'count' => [3 => 1],
        'hp' => 500, 'hp_growth' => 1.2,
        'cost' => ['grain' => 25000], 'cost_growth' => 2.0,
        'time' => 10800, 'time_growth' => 1.75,
        'otag' => [1 => 3, 2 => 5, 3 => 6, 4 => 7, 5 => 8, 6 => 9, 7 => 10, 8 => 11, 9 => 12, 10 => 13, 11 => 14, 12 => 15],
        'xp' => 100, 'xp_growth' => 1.35,
    ],
    [
        'key' => 'buyu_atolyesi', 'name' => 'Büyü Atölyesi', 'category' => 'ordu',
        'grid' => [3, 3], 'max_level' => 7, 'sort' => 34,
        'description' => 'Tılsım (büyü) hazırlar.',
        'lore' => 'Yada taşı suya değdiğinde yağmur yağar derler. Burada o taşın '
            .'tozu, ardıç dumanı ve kam duasıyla tılsım dökülür.',
        'count' => [5 => 1],
        'hp' => 700, 'hp_growth' => 1.2,
        'cost' => ['grain' => 200000], 'cost_growth' => 1.9,
        'time' => 21600, 'time_growth' => 1.7,
        'otag' => [1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 11, 7 => 13],
        'xp' => 160, 'xp_growth' => 1.3,
        'extra' => ['spell_capacity' => [2, 3, 4, 5, 6, 8, 11]],
    ],
    [
        'key' => 'kurgan', 'name' => 'Kurgan', 'category' => 'ozel',
        'grid' => [3, 3], 'max_level' => 10, 'sort' => 35,
        'description' => 'Kahramanları uyandırır ve yükseltir.',
        'lore' => 'Kurgan, atalarının yattığı höyüktür. Kam davulu vurunca '
            .'Asena ulur, Oğuz uyanır; ölüler diriye güç verir.',
        'count' => [7 => 1],
        'hp' => 1200, 'hp_growth' => 1.2,
        'cost' => ['kut' => 5000], 'cost_growth' => 1.85,
        'time' => 43200, 'time_growth' => 1.6,
        'otag' => [1 => 7, 2 => 8, 3 => 9, 4 => 10, 5 => 11, 6 => 12, 7 => 13, 8 => 14, 9 => 15, 10 => 15],
        'xp' => 250, 'xp_growth' => 1.3,
    ],
    [
        'key' => 'boy_otagi', 'name' => 'Boy Otağı', 'category' => 'ozel',
        'grid' => [3, 3], 'max_level' => 10, 'sort' => 36,
        'description' => 'Boya katılmayı ve asker bağışı almayı sağlar. Savunmada bağış askerleri çıkar.',
        'lore' => 'Boyun tuğu burada dalgalanır. Yolu düşen kardeş boy, '
            .'atını bu çadıra bağlar; darda kalana er gönderir.',
        'count' => [3 => 1],
        'hp' => 1000, 'hp_growth' => 1.22,
        'capacity' => 10, 'capacity_growth' => 1.22,
        'cost' => ['gold' => 10000], 'cost_growth' => 2.1,
        'time' => 600, 'time_growth' => 1.9,
        'otag' => [1 => 3, 2 => 6, 3 => 8, 4 => 9, 5 => 10, 6 => 11, 7 => 12, 8 => 13, 9 => 14, 10 => 15],
        'xp' => 80, 'xp_growth' => 1.35,
        'extra' => ['clan_radius' => 7.0],
    ],
    [
        'key' => 'gozcu_kulesi', 'name' => 'Gözcü Kulesi', 'category' => 'ozel',
        'grid' => [2, 2], 'max_level' => 6, 'sort' => 37,
        'description' => 'Dünya haritasında daha uzağı görmeni sağlar.',
        'lore' => 'Bozkırda gören, yaşar. Gözcü tepeye çıkıp ateş yakar; '
            .'duman üç kere kesilirse akın var demektir.',
        'count' => [3 => 1],
        'hp' => 600, 'hp_growth' => 1.2,
        'cost' => ['gold' => 4000], 'cost_growth' => 2.2,
        'time' => 300, 'time_growth' => 2.0,
        'otag' => [1 => 3, 2 => 5, 3 => 7, 4 => 9, 5 => 11, 6 => 13],
        'xp' => 40, 'xp_growth' => 1.4,
        'extra' => ['vision' => [24, 32, 44, 58, 74, 96]],
    ],
    [
        'key' => 'umay_tapinagi', 'name' => 'Umay Tapınağı', 'category' => 'ozel',
        'grid' => [3, 3], 'max_level' => 5, 'sort' => 38,
        'description' => 'Savunma sırasında çevredeki yapıları onarır.',
        'lore' => 'Umay Ana, doğumun ve korumanın iyesidir. Tapınağında yakılan '
            .'ardıç dumanı yaraları kapatır, yıkılanı ayağa kaldırır.',
        'count' => [9 => 1],
        'hp' => 1800, 'hp_growth' => 1.2,
        'cost' => ['kut' => 12000], 'cost_growth' => 1.8,
        'time' => 86400, 'time_growth' => 1.5,
        'otag' => [1 => 9, 2 => 10, 3 => 11, 4 => 13, 5 => 15],
        'xp' => 300, 'xp_growth' => 1.25,
        'extra' => ['repair_per_second' => [12, 18, 26, 36, 50]],
    ],

    /* ----------------------------------------------------------------- */
    /* Savunma                                                            */
    /* ----------------------------------------------------------------- */
    [
        'key' => 'okcu_kulesi', 'name' => 'Okçu Kulesi', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 15, 'sort' => 40,
        'description' => 'Hem yerdeki hem havadaki düşmanı okla vurur.',
        'lore' => 'Kulenin tepesinde bir okçu kız durur; yayı kirişlendiğinde '
            .'rüzgâr bile yön değiştirir derler.',
        'count' => [2 => 1, 4 => 2, 5 => 3, 6 => 4, 7 => 5, 9 => 6, 11 => 8],
        'hp' => 380, 'hp_growth' => 1.16,
        'dps' => 11, 'dps_growth' => 1.21,
        'range' => 10.0,
        'cost' => ['gold' => 1000], 'cost_growth' => 2.1,
        'time' => 60, 'time_growth' => 2.1,
        'otag' => [1 => 2, 3 => 3, 5 => 4, 7 => 6, 9 => 8, 11 => 10, 13 => 12, 15 => 14],
        'xp' => 10, 'xp_growth' => 1.45,
        'extra' => ['targets_air' => true, 'targets_ground' => true, 'attack_speed' => 0.8],
    ],
    [
        'key' => 'ates_kulesi', 'name' => 'Ateş Kulesi', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 15, 'sort' => 41,
        'description' => 'Yerdeki düşmana ağır ateş topu atar. Havayı vuramaz.',
        'lore' => 'Ejder nefesinden ilham alan bu kule, ardıç kömürüyle beslenir. '
            .'Namlusu kızardığında bozkır gündüz gibi aydınlanır.',
        'count' => [1 => 1, 2 => 2, 4 => 3, 5 => 4, 7 => 5, 9 => 6, 11 => 7],
        'hp' => 420, 'hp_growth' => 1.16,
        'dps' => 9, 'dps_growth' => 1.22,
        'range' => 9.0,
        'cost' => ['gold' => 250], 'cost_growth' => 2.15,
        'time' => 30, 'time_growth' => 2.15,
        'otag' => [1 => 1, 3 => 2, 5 => 3, 7 => 5, 9 => 7, 11 => 9, 13 => 11, 15 => 13],
        'xp' => 8, 'xp_growth' => 1.45,
        'extra' => ['targets_air' => false, 'targets_ground' => true, 'attack_speed' => 0.8],
    ],
    [
        'key' => 'mancinik', 'name' => 'Mancınık', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 12, 'sort' => 42,
        'description' => 'Uzağa taş fırlatır, alan hasarı verir. Dibine yaklaşan düşmanı vuramaz.',
        'lore' => 'Kuşatmanın dilinden anlayan ustalar, kayayı göğe savurup '
            .'düşman safını dağıtır. Yakınına sokulana çaresizdir.',
        'count' => [3 => 1, 5 => 2, 7 => 3, 9 => 4],
        'hp' => 400, 'hp_growth' => 1.16,
        'dps' => 4, 'dps_growth' => 1.24,
        'range' => 11.5,
        'cost' => ['gold' => 8000], 'cost_growth' => 2.0,
        'time' => 3600, 'time_growth' => 1.85,
        'otag' => [1 => 3, 2 => 4, 3 => 5, 4 => 6, 5 => 7, 6 => 8, 7 => 9, 8 => 10, 9 => 11, 10 => 12, 11 => 13, 12 => 14],
        'xp' => 60, 'xp_growth' => 1.4,
        'extra' => ['targets_air' => false, 'targets_ground' => true, 'splash' => 1.5, 'min_range' => 4.0, 'attack_speed' => 5.0],
    ],
    [
        'key' => 'yildirim_kulesi', 'name' => 'Yıldırım Kulesi', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 12, 'sort' => 43,
        'description' => 'Kam gücüyle yıldırım çağırır; alan hasarı verir, havayı da vurur.',
        'lore' => 'Tepesine konan yada taşı göğü çeker. Kam ellerini kaldırdığında '
            .'bulutlar toplanır, gök gürler.',
        'count' => [5 => 1, 6 => 2, 7 => 3, 9 => 4, 11 => 5],
        'hp' => 620, 'hp_growth' => 1.16,
        'dps' => 11, 'dps_growth' => 1.23,
        'range' => 7.0,
        'cost' => ['gold' => 180000], 'cost_growth' => 1.9,
        'time' => 14400, 'time_growth' => 1.7,
        'otag' => [1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 10, 7 => 11, 8 => 12, 9 => 13, 10 => 14, 11 => 15, 12 => 15],
        'xp' => 120, 'xp_growth' => 1.35,
        'extra' => ['targets_air' => true, 'targets_ground' => true, 'splash' => 1.0, 'attack_speed' => 1.3],
    ],
    [
        'key' => 'gok_okcusu', 'name' => 'Gök Okçusu', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 11, 'sort' => 44,
        'description' => 'Yalnızca hava birimlerini vurur, ama çok sert vurur.',
        'lore' => 'Konrul\'un kanat sesini duyan gözcüler, uzun yayları göğe çevirir. '
            .'Okun ucuna kartal tüyü bağlanır ki hedefini şaşmasın.',
        'count' => [4 => 1, 5 => 2, 7 => 3, 9 => 4, 12 => 5],
        'hp' => 800, 'hp_growth' => 1.16,
        'dps' => 80, 'dps_growth' => 1.22,
        'range' => 10.0,
        'cost' => ['gold' => 22500], 'cost_growth' => 2.0,
        'time' => 7200, 'time_growth' => 1.8,
        'otag' => [1 => 4, 2 => 5, 3 => 6, 4 => 7, 5 => 8, 6 => 9, 7 => 10, 8 => 11, 9 => 12, 10 => 13, 11 => 14],
        'xp' => 85, 'xp_growth' => 1.4,
        'extra' => ['targets_air' => true, 'targets_ground' => false, 'attack_speed' => 1.0],
    ],
    [
        'key' => 'tilsim_tasi', 'name' => 'Tılsım Taşı', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 8, 'sort' => 45,
        'description' => 'Tek hedefe kilitlenir; vurdukça hasarı artar.',
        'lore' => 'Üzerine dokuz damga kazınmış kara taş, bakışını kime çevirirse '
            .'onu yavaş yavaş yakar. Şahmeran\'ın bile korktuğu söylenir.',
        'count' => [10 => 1, 11 => 2, 13 => 3],
        'hp' => 1500, 'hp_growth' => 1.15,
        'dps' => 30, 'dps_growth' => 1.25,
        'range' => 9.0,
        'cost' => ['gold' => 3200000], 'cost_growth' => 1.6,
        'time' => 345600, 'time_growth' => 1.35,
        'otag' => [1 => 10, 2 => 11, 3 => 12, 4 => 12, 5 => 13, 6 => 14, 7 => 15, 8 => 15],
        'xp' => 400, 'xp_growth' => 1.2,
        'extra' => ['targets_air' => true, 'targets_ground' => true, 'attack_speed' => 0.4, 'ramp_up' => true],
    ],
    [
        'key' => 'kartal_gozu', 'name' => 'Kartal Gözü', 'category' => 'savunma',
        'grid' => [4, 4], 'max_level' => 5, 'sort' => 46,
        'description' => 'Obanın her yerine ulaşan dev taş atar; büyük orduları hedefler.',
        'lore' => 'Zirveye kurulan kartal pençesi biçimli mancınık, Toğrul\'un '
            .'gözüyle nişan alır. Yalnız kalabalığa ateş eder.',
        'count' => [11 => 1],
        'hp' => 4000, 'hp_growth' => 1.14,
        'dps' => 100, 'dps_growth' => 1.18,
        'range' => 50.0,
        'cost' => ['gold' => 6000000], 'cost_growth' => 1.4,
        'time' => 604800, 'time_growth' => 1.3,
        'otag' => [1 => 11, 2 => 12, 3 => 13, 4 => 14, 5 => 15],
        'xp' => 700, 'xp_growth' => 1.2,
        'extra' => ['targets_air' => true, 'targets_ground' => true, 'splash' => 2.5, 'min_range' => 7.0, 'attack_speed' => 6.0],
    ],
    [
        'key' => 'kurt_ini', 'name' => 'Kurt İni', 'category' => 'savunma',
        'grid' => [3, 3], 'max_level' => 7, 'sort' => 47,
        'description' => 'Yıkıldığında içinden börüler (kurtlar) çıkar.',
        'lore' => 'Asena\'nın soyundan gelen bozkurtlar bu inde beslenir. '
            .'İn yıkılırsa kurtlar serbest kalır — ve kurt, yurdunu bırakmaz.',
        'count' => [6 => 1, 8 => 2, 10 => 3],
        'hp' => 1200, 'hp_growth' => 1.17,
        'cost' => ['gold' => 400000], 'cost_growth' => 1.85,
        'time' => 43200, 'time_growth' => 1.6,
        'otag' => [1 => 6, 2 => 7, 3 => 8, 4 => 10, 5 => 12, 6 => 13, 7 => 15],
        'xp' => 200, 'xp_growth' => 1.3,
        'extra' => [
            'spawn_counts' => [3, 4, 5, 6, 7, 8, 10],
            'spawn_key'    => 'boru',
        ],
    ],

    /* ----------------------------------------------------------------- */
    /* Duvar ve tuzaklar                                                  */
    /* ----------------------------------------------------------------- */
    [
        'key' => 'surlar', 'name' => 'Surlar', 'category' => 'duvar',
        'grid' => [1, 1], 'max_level' => 15, 'sort' => 50,
        'description' => 'Askerleri yavaşlatır, yolu kapatır.',
        'lore' => 'Önce hendek, sonra kazık, sonra taş... Oba büyüdükçe surlar '
            .'yükselir; her taşın altına bir dua gömülür.',
        'count' => [2 => 25, 3 => 50, 4 => 75, 5 => 100, 6 => 125, 7 => 175, 8 => 225, 9 => 250, 10 => 275, 11 => 300, 12 => 325, 13 => 350],
        'hp' => 300, 'hp_growth' => 1.26,
        'cost' => ['gold' => 50], 'cost_growth' => 2.6,
        'time' => 0,
        'otag' => [1 => 2, 3 => 3, 5 => 5, 7 => 7, 9 => 9, 11 => 11, 13 => 12, 15 => 13],
        'xp' => 1, 'xp_growth' => 1.6,
    ],
    [
        'key' => 'kapan', 'name' => 'Kapan', 'category' => 'tuzak',
        'grid' => [1, 1], 'max_level' => 8, 'sort' => 51,
        'description' => 'Üzerine basan yer askerlerine patlayarak hasar verir.',
        'lore' => 'Avcıların kurt kapanından esinlenen tuzak; toprağa gömülür, '
            .'üstüne kuru ot serilir. Basan, bir daha kalkmaz.',
        'count' => [3 => 2, 5 => 4, 7 => 6, 9 => 8],
        'hp' => 1, 'hp_growth' => 1.0,
        'cost' => ['gold' => 400], 'cost_growth' => 2.2,
        'time' => 0,
        'otag' => [1 => 3, 2 => 5, 3 => 7, 4 => 9, 5 => 11, 6 => 12, 7 => 13, 8 => 14],
        'xp' => 4, 'xp_growth' => 1.4,
        'extra' => ['trap_radius' => 2.0, 'trap_damage_base' => 150, 'trap_damage_growth' => 1.35, 'trap_air' => false],
    ],
    [
        'key' => 'sivri_kazik', 'name' => 'Sivri Kazık', 'category' => 'tuzak',
        'grid' => [1, 1], 'max_level' => 6, 'sort' => 52,
        'description' => 'Yerden fırlayıp askerleri oba dışına savurur.',
        'lore' => 'Hendeğin dibine çakılan kazıklar, yayla gerilir. '
            .'Tetiğe basan atlı, geldiği yoldan geri uçar.',
        'count' => [4 => 2, 6 => 4, 8 => 6],
        'hp' => 1, 'hp_growth' => 1.0,
        'cost' => ['gold' => 2000], 'cost_growth' => 2.1,
        'time' => 0,
        'otag' => [1 => 4, 2 => 6, 3 => 8, 4 => 10, 5 => 12, 6 => 14],
        'xp' => 6, 'xp_growth' => 1.4,
        'extra' => ['trap_radius' => 1.8, 'trap_damage_base' => 500, 'trap_damage_growth' => 1.4, 'trap_air' => false],
    ],
    [
        'key' => 'hava_kapani', 'name' => 'Hava Kapanı', 'category' => 'tuzak',
        'grid' => [1, 1], 'max_level' => 6, 'sort' => 53,
        'description' => 'Göğe ağ fırlatır; yalnızca hava birimlerini vurur.',
        'lore' => 'Kuş avcılarının ağı, kam düğümüyle bağlanınca Toğrul\'u bile tutar.',
        'count' => [5 => 2, 7 => 4, 9 => 6],
        'hp' => 1, 'hp_growth' => 1.0,
        'cost' => ['gold' => 6000], 'cost_growth' => 2.0,
        'time' => 0,
        'otag' => [1 => 5, 2 => 7, 3 => 9, 4 => 11, 5 => 13, 6 => 15],
        'xp' => 8, 'xp_growth' => 1.35,
        'extra' => ['trap_radius' => 2.5, 'trap_damage_base' => 200, 'trap_damage_growth' => 1.35, 'trap_air' => true],
    ],
    [
        'key' => 'devrilen_tas', 'name' => 'Devrilen Taş', 'category' => 'tuzak',
        'grid' => [2, 2], 'max_level' => 5, 'sort' => 54,
        'description' => 'Geniş alanda çok ağır hasar veren dev tuzak.',
        'lore' => 'Yamaca dayanmış kaya, bir kıl kadar ipe bağlıdır. '
            .'O ip kesilince vadiyi gök gürültüsü doldurur.',
        'count' => [8 => 1, 10 => 2, 12 => 3],
        'hp' => 1, 'hp_growth' => 1.0,
        'cost' => ['gold' => 120000], 'cost_growth' => 1.9,
        'time' => 0,
        'otag' => [1 => 8, 2 => 10, 3 => 12, 4 => 13, 5 => 15],
        'xp' => 30, 'xp_growth' => 1.3,
        'extra' => ['trap_radius' => 3.5, 'trap_damage_base' => 900, 'trap_damage_growth' => 1.35, 'trap_air' => false],
    ],
    [
        'key' => 'albiz_tuzagi', 'name' => 'Albız Tuzağı', 'category' => 'tuzak',
        'grid' => [1, 1], 'max_level' => 5, 'sort' => 55,
        'description' => 'Tetiklendiğinde Albız gölgeleri çağırır.',
        'lore' => 'Albız, al basan kötü ruhtur. Kam onu bir kemiğe hapseder; '
            .'kemik kırılınca gölgeler ayağa kalkar.',
        'count' => [9 => 2, 11 => 4],
        'hp' => 1, 'hp_growth' => 1.0,
        'cost' => ['kut' => 4000], 'cost_growth' => 1.9,
        'time' => 0,
        'otag' => [1 => 9, 2 => 10, 3 => 11, 4 => 13, 5 => 15],
        'xp' => 40, 'xp_growth' => 1.3,
        'extra' => [
            'trap_radius'  => 2.5,
            'trap_air'     => false,
            'spawn_counts' => [4, 5, 6, 7, 8],
            'spawn_key'    => 'albasti_golgesi',
        ],
    ],
];
