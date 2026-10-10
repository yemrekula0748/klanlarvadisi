<?php

/*
|--------------------------------------------------------------------------
| Dünya Bölgeleri — Türk Mitolojisinin Coğrafyası
|--------------------------------------------------------------------------
|
| Konumlar dünya genişliğinin oranı olarak verilir (0..1), böylece harita
| boyutu değişse de bölgeler yerinde kalır. Boylar bölgeleri ele geçirebilir
| ve `bonus` çarpanlarından yararlanır.
|
*/

return [
    ['key' => 'otuken', 'name' => 'Ötüken', 'biome' => 'cayir',
        'at' => [0.50, 0.50], 'radius' => 0.085, 'color' => '#ffd166',
        'terrain' => ['e' => 0.46, 't' => 0.52, 'm' => 0.55, 'strength' => 0.85],
        'bonus' => ['kut' => 1.15, 'gold' => 1.05],
        'lore' => 'Dünyanın göbeği, göğün direğinin yere değdiği kutsal orman. '
            .'Kağanlar burada tahta çıkar; buraya sahip olan boy, bozkırın sözünü söyler.'],

    ['key' => 'altay', 'name' => 'Altay Dağları', 'biome' => 'dag',
        'at' => [0.33, 0.30], 'radius' => 0.11, 'color' => '#9aa7b4',
        'terrain' => ['e' => 0.84, 't' => 0.26, 'm' => 0.50, 'strength' => 0.9],
        'bonus' => ['iron' => 1.25],
        'lore' => 'Altın dağ. Damarlarında demir ve altın akar; eteklerinde '
            .'demirci ocakları hiç sönmez.'],

    ['key' => 'ergenekon', 'name' => 'Ergenekon', 'biome' => 'dag',
        'at' => [0.24, 0.44], 'radius' => 0.075, 'color' => '#e07a5f',
        'terrain' => ['e' => 0.76, 't' => 0.34, 'm' => 0.48, 'strength' => 0.9],
        'bonus' => ['iron' => 1.2, 'gold' => 1.1],
        'lore' => 'Dört yanı demir dağlarla çevrili vadi. Soyun kırıldığı gün '
            .'buraya sığınıldı; demirci dağı eritince yeniden dünyaya çıkıldı.'],

    ['key' => 'demirkapi', 'name' => 'Demirkapı', 'biome' => 'dag',
        'at' => [0.17, 0.56], 'radius' => 0.055, 'color' => '#7a8694',
        'terrain' => ['e' => 0.79, 't' => 0.40, 'm' => 0.45, 'strength' => 0.9],
        'bonus' => ['iron' => 1.3],
        'lore' => 'Ergenekon\'un eritilmiş kapısı. Hâlâ sıcaktır derler; '
            .'dokunan elin izi demire kalır.'],

    ['key' => 'bozkir', 'name' => 'Uçsuz Bozkır', 'biome' => 'bozkir',
        'at' => [0.62, 0.44], 'radius' => 0.14, 'color' => '#8fa05a',
        'terrain' => ['e' => 0.38, 't' => 0.52, 'm' => 0.4, 'strength' => 0.8],
        'bonus' => ['grain' => 1.2],
        'lore' => 'Göz alabildiğine ot, ot, ot. At burada doğar, burada ölür. '
            .'Yurt kuranın da, yurdunu bozanın da yolu buradan geçer.'],

    ['key' => 'yada_golu', 'name' => 'Yada Gölü', 'biome' => 'bataklik',
        'at' => [0.71, 0.62], 'radius' => 0.065, 'color' => '#4a8fa8',
        'terrain' => ['e' => 0.19, 't' => 0.50, 'm' => 0.88, 'strength' => 0.92],
        'bonus' => ['kut' => 1.25],
        'lore' => 'Suyuna atılan taş yağmur çağırır. Kamlar yada taşını '
            .'bu gölün dibinden çıkarır; ama her dalan geri dönmez.'],

    ['key' => 'kok_deniz', 'name' => 'Kök Deniz', 'biome' => 'deniz',
        'at' => [0.86, 0.30], 'radius' => 0.12, 'color' => '#1b4a6b',
        'terrain' => ['e' => 0.06, 't' => 0.58, 'm' => 0.92, 'strength' => 0.8],
        'bonus' => ['gold' => 1.15],
        'lore' => 'Gök renkli deniz. Ötesinde ne olduğunu kimse bilmez; '
            .'kıyısında kurulan pazarlar dünyanın her malını görür.'],

    ['key' => 'karanlik_orman', 'name' => 'Karanlık Orman', 'biome' => 'orman',
        'at' => [0.44, 0.74], 'radius' => 0.10, 'color' => '#2f5d3a',
        'terrain' => ['e' => 0.46, 't' => 0.44, 'm' => 0.88, 'strength' => 0.88],
        'bonus' => ['grain' => 1.1, 'kut' => 1.1],
        'lore' => 'Güneşin yere değmediği ağaçlar. İçinde Karakoncolos, '
            .'Albastı ve adı anılmayanlar yaşar. Gece girene yol görünmez.'],

    ['key' => 'kizil_col', 'name' => 'Kızıl Çöl', 'biome' => 'col',
        'at' => [0.79, 0.80], 'radius' => 0.11, 'color' => '#c58a4a',
        'terrain' => ['e' => 0.36, 't' => 0.92, 'm' => 0.10, 'strength' => 0.9],
        'bonus' => ['gold' => 1.25],
        'lore' => 'Kumun altında eski şehirler var derler. Sıcakta yürüyen '
            .'kervanlar, serapta kendi obalarını görür.'],

    ['key' => 'tanri_dagi', 'name' => 'Tanrı Dağı', 'biome' => 'kar',
        'at' => [0.30, 0.16], 'radius' => 0.085, 'color' => '#e8eef2',
        'terrain' => ['e' => 0.97, 't' => 0.04, 'm' => 0.52, 'strength' => 0.93],
        'bonus' => ['kut' => 1.3],
        'lore' => 'Zirvesi hiç görülmemiş ak dağ. Ülgen\'in katına en yakın yer; '
            .'kamlar buraya çıkarken dokuz kez dinlenir.'],

    ['key' => 'erlik_yurdu', 'name' => 'Erlik Yurdu', 'biome' => 'bataklik',
        'at' => [0.56, 0.88], 'radius' => 0.085, 'color' => '#4a2d4a',
        'terrain' => ['e' => 0.24, 't' => 0.44, 'm' => 0.82, 'strength' => 0.9],
        'bonus' => ['iron' => 1.15, 'kut' => 1.2],
        'lore' => 'Yerin dibine açılan dokuz kuyu. Erlik Han\'ın kara otağı '
            .'buradadır; dumanı göğe değil, aşağı çeker.'],

    ['key' => 'umay_bahcesi', 'name' => 'Umay Bahçesi', 'biome' => 'cayir',
        'at' => [0.65, 0.22], 'radius' => 0.07, 'color' => '#8fd1a0',
        'terrain' => ['e' => 0.43, 't' => 0.62, 'm' => 0.58, 'strength' => 0.85],
        'bonus' => ['grain' => 1.3],
        'lore' => 'Umay Ana\'nın elinin değdiği topraklar. Tohum ne zaman '
            .'atılsa biter, hangi mevsim olursa olsun.'],

    ['key' => 'kurgan_ovasi', 'name' => 'Kurgan Ovası', 'biome' => 'bozkir',
        'at' => [0.42, 0.60], 'radius' => 0.075, 'color' => '#a8936a',
        'terrain' => ['e' => 0.39, 't' => 0.56, 'm' => 0.38, 'strength' => 0.8],
        'bonus' => ['kut' => 1.15, 'grain' => 1.05],
        'lore' => 'Bin höyük, bin kağan. Taş babalar yüzlerini doğuya çevirmiş, '
            .'ellerinde kadeh, bekliyorlar.'],

    ['key' => 'temir_tau', 'name' => 'Temir Tau', 'biome' => 'tayga',
        'at' => [0.20, 0.72], 'radius' => 0.08, 'color' => '#3b6b52',
        'terrain' => ['e' => 0.56, 't' => 0.14, 'm' => 0.62, 'strength' => 0.88],
        'bonus' => ['iron' => 1.2, 'grain' => 1.05],
        'lore' => 'Demir dağ. Ormanı kara, suyu demir tadındadır. '
            .'Baltası keskin olanın ocağı burada söğümez.'],

    ['key' => 'yer_su', 'name' => 'Yer-Su Vadisi', 'biome' => 'cayir',
        'at' => [0.50, 0.34], 'radius' => 0.065, 'color' => '#6f9b4a',
        'terrain' => ['e' => 0.44, 't' => 0.5, 'm' => 0.66, 'strength' => 0.8],
        'bonus' => ['grain' => 1.15, 'gold' => 1.05],
        'lore' => 'Yer ve suyun iyeleri burada toplanır. Pınarına süt dökmeden '
            .'su içilmez; ağacına bez bağlanmadan geçilmez.'],

    ['key' => 'ak_dag', 'name' => 'Ak Dağ', 'biome' => 'kar',
        'at' => [0.88, 0.58], 'radius' => 0.075, 'color' => '#dfe8ee',
        'terrain' => ['e' => 0.93, 't' => 0.08, 'm' => 0.52, 'strength' => 0.92],
        'bonus' => ['iron' => 1.1, 'kut' => 1.1],
        'lore' => 'Karı hiç erimeyen dağ. Hüma kuşunun yuvası zirvesindedir; '
            .'gölgesi düşen oba yedi yıl bolluk görür.'],
];
