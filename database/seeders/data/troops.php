<?php

/*
|--------------------------------------------------------------------------
| Asker Kataloğu — Türk Mitolojisi
|--------------------------------------------------------------------------
|
| move_speed : hücre/saniye * 10  (16 => 1.6 hücre/sn)
| pref       : yok | savunma | duvar | kaynak | otag
| mult       : tercih edilen hedefe hasar çarpanı
| hp / dps   : 1. seviye değerleri, her seviyede *_growth ile artar
| demirci    : seviye => gereken Demirci seviyesi
|
*/

return [
    /* --------------------------- Kışla (ak) --------------------------- */
    [
        'key' => 'ceri', 'name' => 'Çeri', 'category' => 'yaya',
        'housing' => 1, 'train' => 20, 'cost' => ['grain' => 25],
        'max_level' => 10, 'unlock' => 1, 'sort' => 1,
        'description' => 'Obanın temel eri. Ucuz, hızlı yetişir, kalabalıkla kazanır.',
        'lore' => 'Çeri, bozkırın sıradan yiğididir: çarığı yırtık, yüreği pek. '
            .'Tuğ kalktığında ilk o koşar, son o döner.',
        'hp' => 50, 'hp_growth' => 1.21, 'dps' => 11, 'dps_growth' => 1.18,
        'speed' => 16, 'range' => 0.5, 'attack_speed' => 1.0, 'pref' => 'yok',
    ],
    [
        'key' => 'okcu_kiz', 'name' => 'Okçu Kız', 'category' => 'yaya',
        'housing' => 1, 'train' => 25, 'cost' => ['grain' => 50],
        'max_level' => 10, 'unlock' => 2, 'sort' => 2,
        'description' => 'Uzaktan ok atar; surların arkasındaki yapıları vurabilir.',
        'lore' => 'Bozkırda kız da erkek de yay çeker. Tomris Hatun\'un '
            .'kızlarından olan bu okçular, at üstünde dönerek nişan alır.',
        'hp' => 24, 'hp_growth' => 1.21, 'dps' => 12, 'dps_growth' => 1.18,
        'speed' => 24, 'range' => 3.5, 'attack_speed' => 1.0, 'pref' => 'yok',
    ],
    [
        'key' => 'gurzcu', 'name' => 'Gürzcü', 'category' => 'yaya',
        'housing' => 2, 'train' => 60, 'cost' => ['grain' => 350],
        'max_level' => 9, 'unlock' => 3, 'sort' => 3,
        'description' => 'Surlara karşı kullanılır; duvara verdiği hasar 25 katıdır.',
        'lore' => 'Gürzü omzunda taşıyan bu devasa adamlar, taşı taşa vurup '
            .'yol açar. "Duvar mı? Duvar bir yanlış anlaşılmadır" derler.',
        'hp' => 160, 'hp_growth' => 1.2, 'dps' => 10, 'dps_growth' => 1.42,
        'speed' => 28, 'range' => 0.5, 'attack_speed' => 1.0,
        'pref' => 'duvar', 'mult' => 40,
    ],
    [
        'key' => 'boru', 'name' => 'Börü', 'category' => 'atli',
        'housing' => 2, 'train' => 40, 'cost' => ['grain' => 200],
        'max_level' => 10, 'unlock' => 4, 'sort' => 4,
        'description' => 'Hızlı bozkurt. Sürü hâlinde saldırır, çabuk sonuç alır.',
        'lore' => 'Asena\'nın soyundan gelen bozkurtlar, savaşa çağrılınca '
            .'uluyarak gelir. Hiçbir ordu kurt sürüsü kadar hızlı değildir.',
        'hp' => 160, 'hp_growth' => 1.22, 'dps' => 24, 'dps_growth' => 1.18,
        'speed' => 32, 'range' => 0.5, 'attack_speed' => 0.8, 'pref' => 'yok',
    ],
    [
        'key' => 'tepegoz', 'name' => 'Tepegöz', 'category' => 'yaya',
        'housing' => 5, 'train' => 120, 'cost' => ['grain' => 1200],
        'max_level' => 10, 'unlock' => 5, 'sort' => 5,
        'description' => 'Tek gözlü dev. Çok dayanıklıdır, savunma yapılarına yönelir.',
        'lore' => 'Oğuz destanlarının korkulu rüyası: tek gözü alnının ortasında, '
            .'sesi vadiyi titreten dev. Yalnız kuleleri hedef alır, kalanı ezer geçer.',
        'hp' => 620, 'hp_growth' => 1.2, 'dps' => 15, 'dps_growth' => 1.18,
        'speed' => 12, 'range' => 1.0, 'attack_speed' => 2.0,
        'pref' => 'savunma', 'mult' => 1.5,
    ],
    [
        'key' => 'alp', 'name' => 'Alp', 'category' => 'yaya',
        'housing' => 4, 'train' => 100, 'cost' => ['grain' => 900],
        'max_level' => 9, 'unlock' => 7, 'sort' => 6,
        'description' => 'Zırhlı seçkin savaşçı; hem dayanıklı hem vurucu.',
        'lore' => 'Alp olmak için dokuz sınavdan geçmek gerekir: açlık, susuzluk, '
            .'uykusuzluk, yalnızlık... Dokuzuncusunu kimse anlatmamıştır.',
        'hp' => 560, 'hp_growth' => 1.21, 'dps' => 32, 'dps_growth' => 1.18,
        'speed' => 16, 'range' => 0.6, 'attack_speed' => 0.9, 'pref' => 'yok',
    ],
    [
        'key' => 'kurt_binici', 'name' => 'Kurt Binici', 'category' => 'atli',
        'housing' => 4, 'train' => 110, 'cost' => ['grain' => 1100],
        'max_level' => 8, 'unlock' => 8, 'sort' => 7,
        'description' => 'Kurda binen akıncı; surları aşmadan kaynaklara dalar.',
        'lore' => 'Kurda binmek, kurtla konuşmayı bilmek demektir. '
            .'Bu akıncılar obaya girmeden ambarın yerini bulur.',
        'hp' => 380, 'hp_growth' => 1.21, 'dps' => 60, 'dps_growth' => 1.18,
        'speed' => 40, 'range' => 0.6, 'attack_speed' => 0.7,
        'pref' => 'kaynak', 'mult' => 2.0,
    ],
    [
        'key' => 'baksi', 'name' => 'Baksı', 'category' => 'yaya',
        'housing' => 14, 'train' => 300, 'cost' => ['grain' => 5000],
        'max_level' => 7, 'unlock' => 9, 'sort' => 8,
        'description' => 'Savaş kamı. Saldırmaz; çevresindeki askerleri iyileştirir.',
        'lore' => 'Baksı, davulunu vurarak ruhları çağırır; yarayı kapatır, '
            .'korkuyu dağıtır. Düşmana el kaldırmak ona yasaktır.',
        'hp' => 500, 'hp_growth' => 1.2, 'dps' => 0, 'dps_growth' => 1.0,
        'speed' => 16, 'range' => 4.0, 'attack_speed' => 1.0, 'pref' => 'yok',
        'extra' => ['heal_per_second_base' => 36, 'heal_growth' => 1.18],
    ],
    [
        'key' => 'togrul', 'name' => 'Toğrul', 'category' => 'karanlik',
        'housing' => 2, 'train' => 45, 'cost' => ['iron' => 6],
        'max_level' => 9, 'unlock' => 1, 'sort' => 9, 'dark' => true,
        'description' => 'Doğan kuşu. Havadan saldırır, yer savunmasından etkilenmez.',
        'lore' => 'Toğrul, kağanın avcı kuşudur. Gözü keskin, kanadı sessizdir; '
            .'kuleye konan okçuyu fark ettirmeden alır.',
        'hp' => 34, 'hp_growth' => 1.18, 'dps' => 35, 'dps_growth' => 1.18,
        'speed' => 48, 'range' => 1.6, 'attack_speed' => 0.5, 'pref' => 'yok',
        'is_air' => true,
    ],
    [
        'key' => 'konrul', 'name' => 'Konrul', 'category' => 'hava',
        'housing' => 20, 'train' => 420, 'cost' => ['grain' => 3000],
        'max_level' => 8, 'unlock' => 10, 'sort' => 10,
        'description' => 'Çift başlı kartal. Havadan ağır hasar verir, dayanıklıdır.',
        'lore' => 'İki başlı Konrul, göğün iki yönünü birden görür. '
            .'Kağan sancağına nakşedilmiştir; kanat açtığında gölgesi obayı örter.',
        'hp' => 1900, 'hp_growth' => 1.15, 'dps' => 140, 'dps_growth' => 1.2,
        'speed' => 24, 'range' => 1.5, 'attack_speed' => 1.0, 'pref' => 'yok',
        'is_air' => true,
    ],
    [
        'key' => 'mancinik_arabasi', 'name' => 'Mancınık Arabası', 'category' => 'kusatma',
        'housing' => 25, 'train' => 480, 'cost' => ['grain' => 9000],
        'max_level' => 6, 'unlock' => 11, 'sort' => 11,
        'description' => 'Kuşatma makinesi. Uzaktan surları ve kuleleri döver.',
        'lore' => 'Öküzlerin çektiği bu ağır araba, obanın kapısına dayandığında '
            .'içindeki ustalar "sabır" der: üç taş, bir duvar.',
        'hp' => 2400, 'hp_growth' => 1.18, 'dps' => 70, 'dps_growth' => 1.18,
        'speed' => 10, 'range' => 6.0, 'attack_speed' => 3.0,
        'pref' => 'savunma', 'mult' => 1.4, 'splash' => 1.5,
    ],
    [
        'key' => 'zumrudu_anka', 'name' => 'Zümrüdüanka', 'category' => 'hava',
        'housing' => 25, 'train' => 600, 'cost' => ['kut' => 250],
        'max_level' => 5, 'unlock' => 12, 'sort' => 12,
        'description' => 'Hüma kuşu. Küllerinden doğar: ilk ölümünde yeniden dirilir.',
        'lore' => 'Kaf Dağı\'nın ardında yaşayan Zümrüdüanka, gölgesi düşene '
            .'devlet verir. Yanar, küle döner, külünden yeniden doğar.',
        'hp' => 3000, 'hp_growth' => 1.15, 'dps' => 170, 'dps_growth' => 1.18,
        'speed' => 28, 'range' => 1.8, 'attack_speed' => 0.9, 'pref' => 'yok',
        'is_air' => true,
        'extra' => ['revive_percent' => 50],
    ],

    /* ------------------------ Kara Kışla (kara) ----------------------- */
    [
        'key' => 'karakoncolos', 'name' => 'Karakoncolos', 'category' => 'karanlik',
        'housing' => 8, 'train' => 210, 'cost' => ['iron' => 45],
        'max_level' => 7, 'unlock' => 1, 'sort' => 20, 'dark' => true,
        'description' => 'Kış şeytanı. Yaklaştığı askerleri ve yapıları dondurarak yavaşlatır.',
        'lore' => 'Zemheri gecelerinde kapı çalar, adını sorar. Yanlış cevap '
            .'verenin soluğu buz tutar. Karakoncolos geçtiği yere kış getirir.',
        'hp' => 980, 'hp_growth' => 1.2, 'dps' => 36, 'dps_growth' => 1.18,
        'speed' => 14, 'range' => 0.8, 'attack_speed' => 1.2, 'pref' => 'yok',
        'extra' => ['slow_percent' => 35, 'slow_radius' => 3.0],
    ],
    [
        'key' => 'albasti', 'name' => 'Albastı', 'category' => 'karanlik',
        'housing' => 5, 'train' => 150, 'cost' => ['iron' => 35],
        'max_level' => 7, 'unlock' => 2, 'sort' => 21, 'dark' => true,
        'description' => 'Al karısı. Savunma kulelerine musallat olur, hızlı hareket eder.',
        'lore' => 'Suyun kenarında saçını tarayan al giysili kadın... '
            .'Kulede nöbet tutan okçunun kulağına adını fısıldar, nişanı şaşar.',
        'hp' => 520, 'hp_growth' => 1.21, 'dps' => 60, 'dps_growth' => 1.18,
        'speed' => 36, 'range' => 0.6, 'attack_speed' => 0.6,
        'pref' => 'savunma', 'mult' => 1.5,
    ],
    [
        'key' => 'albasti_golgesi', 'name' => 'Albız Gölgesi', 'category' => 'karanlik',
        'housing' => 1, 'train' => 30, 'cost' => ['iron' => 8],
        'max_level' => 5, 'unlock' => 99, 'sort' => 22, 'dark' => true,
        'description' => 'Albız Tuzağı\'ndan çıkan gölge. Yalnızca savunmada görülür.',
        'lore' => 'Ne eti ne kemiği var; yalnız niyeti var. Kam onu bir kemiğe '
            .'hapseder, kemik kırılınca ayağa kalkar.',
        'hp' => 110, 'hp_growth' => 1.2, 'dps' => 45, 'dps_growth' => 1.2,
        'speed' => 32, 'range' => 0.5, 'attack_speed' => 0.7, 'pref' => 'yok',
    ],
    [
        'key' => 'sahmeran', 'name' => 'Şahmeran', 'category' => 'karanlik',
        'housing' => 10, 'train' => 300, 'cost' => ['iron' => 90],
        'max_level' => 6, 'unlock' => 4, 'sort' => 23, 'dark' => true,
        'description' => 'Yılanların şahı. Zehirli nefesi alan hasarı verir.',
        'lore' => 'Yarı kadın yarı yılan, yerin altındaki sarayında hükmeder. '
            .'Sırrını öğrenen şifa bulur; ihanet eden zehir içer.',
        'hp' => 1250, 'hp_growth' => 1.2, 'dps' => 70, 'dps_growth' => 1.18,
        'speed' => 18, 'range' => 2.2, 'attack_speed' => 1.1, 'pref' => 'yok',
        'splash' => 1.4,
    ],
    [
        'key' => 'erlik_comezi', 'name' => 'Erlik Çömezi', 'category' => 'karanlik',
        'housing' => 10, 'train' => 330, 'cost' => ['iron' => 120],
        'max_level' => 6, 'unlock' => 6, 'sort' => 24, 'dark' => true,
        'description' => 'Yeraltının kamı. Öldüğünde patlar ve çevresine hasar verir.',
        'lore' => 'Erlik Han\'ın dokuz oğlunun hizmetkârları. Yüzlerini kimse '
            .'görmedi; çünkü bakan geri dönmedi.',
        'hp' => 620, 'hp_growth' => 1.2, 'dps' => 85, 'dps_growth' => 1.18,
        'speed' => 16, 'range' => 1.2, 'attack_speed' => 1.3, 'pref' => 'yok',
        'extra' => ['death_damage_base' => 400, 'death_radius' => 2.5],
    ],
    [
        'key' => 'yelbegen', 'name' => 'Yelbegen', 'category' => 'karanlik',
        'housing' => 28, 'train' => 660, 'cost' => ['iron' => 300],
        'max_level' => 5, 'unlock' => 8, 'sort' => 25, 'dark' => true,
        'description' => 'Yedi başlı ejder. Havadan geniş alana ateş püskürtür.',
        'lore' => 'Yelbegen yedi başlıdır; her başı bir kötülük söyler. '
            .'Altay masallarında ayı yutan odur — gökte kaybolan ay onun ağzındadır.',
        'hp' => 3400, 'hp_growth' => 1.15, 'dps' => 200, 'dps_growth' => 1.18,
        'speed' => 20, 'range' => 2.5, 'attack_speed' => 1.4, 'pref' => 'yok',
        'is_air' => true, 'splash' => 2.0,
    ],
];
