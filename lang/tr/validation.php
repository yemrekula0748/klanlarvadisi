<?php

/*
|--------------------------------------------------------------------------
| Doğrulama İletileri
|--------------------------------------------------------------------------
|
| Oyun Türkçe oynanır (APP_LOCALE=tr). Bu dosya olmadan Laravel çeviri
| anahtarını ham döndürür ve Unity istemcisi oyuncuya "validation.required"
| yazardı. Buradaki iletiler doğrudan ekranda görünür.
|
*/

return [

    'accepted'             => ':attribute kabul edilmelidir.',
    'active_url'           => ':attribute geçerli bir adres değil.',
    'after'                => ':attribute :date tarihinden sonra olmalıdır.',
    'after_or_equal'       => ':attribute :date tarihinden sonra ya da aynı olmalıdır.',
    'alpha'                => ':attribute yalnızca harf içerebilir.',
    'alpha_dash'           => ':attribute yalnızca harf, sayı, tire ve alt çizgi içerebilir.',
    'alpha_num'            => ':attribute yalnızca harf ve sayı içerebilir.',
    'array'                => ':attribute bir dizi olmalıdır.',
    'before'               => ':attribute :date tarihinden önce olmalıdır.',
    'before_or_equal'      => ':attribute :date tarihinden önce ya da aynı olmalıdır.',
    'between'              => [
        'array'   => ':attribute :min ile :max arasında öğe içermelidir.',
        'file'    => ':attribute :min ile :max kilobayt arasında olmalıdır.',
        'numeric' => ':attribute :min ile :max arasında olmalıdır.',
        'string'  => ':attribute :min ile :max karakter arasında olmalıdır.',
    ],
    'boolean'              => ':attribute yalnızca doğru ya da yanlış olabilir.',
    'confirmed'            => ':attribute tekrarı eşleşmiyor.',
    'date'                 => ':attribute geçerli bir tarih değil.',
    'date_equals'          => ':attribute :date tarihine eşit olmalıdır.',
    'date_format'          => ':attribute :format biçimine uymuyor.',
    'different'            => ':attribute ile :other farklı olmalıdır.',
    'digits'               => ':attribute :digits haneli olmalıdır.',
    'digits_between'       => ':attribute :min ile :max hane arasında olmalıdır.',
    'distinct'             => ':attribute yinelenen bir değer taşıyor.',
    'email'                => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'exists'               => 'Seçilen :attribute geçersiz.',
    'filled'               => ':attribute boş bırakılamaz.',
    'gt'                   => [
        'array'   => ':attribute :value öğeden fazlasını içermelidir.',
        'file'    => ':attribute :value kilobayttan büyük olmalıdır.',
        'numeric' => ':attribute :value değerinden büyük olmalıdır.',
        'string'  => ':attribute :value karakterden uzun olmalıdır.',
    ],
    'gte'                  => [
        'array'   => ':attribute en az :value öğe içermelidir.',
        'file'    => ':attribute en az :value kilobayt olmalıdır.',
        'numeric' => ':attribute en az :value olmalıdır.',
        'string'  => ':attribute en az :value karakter olmalıdır.',
    ],
    'in'                   => 'Seçilen :attribute geçersiz.',
    'in_array'             => ':attribute :other içinde bulunmuyor.',
    'integer'              => ':attribute tam sayı olmalıdır.',
    'ip'                   => ':attribute geçerli bir IP adresi olmalıdır.',
    'json'                 => ':attribute geçerli bir JSON metni olmalıdır.',
    'lt'                   => [
        'array'   => ':attribute :value öğeden azını içermelidir.',
        'file'    => ':attribute :value kilobayttan küçük olmalıdır.',
        'numeric' => ':attribute :value değerinden küçük olmalıdır.',
        'string'  => ':attribute :value karakterden kısa olmalıdır.',
    ],
    'lte'                  => [
        'array'   => ':attribute en çok :value öğe içermelidir.',
        'file'    => ':attribute en çok :value kilobayt olmalıdır.',
        'numeric' => ':attribute en çok :value olmalıdır.',
        'string'  => ':attribute en çok :value karakter olmalıdır.',
    ],
    'max'                  => [
        'array'   => ':attribute en çok :max öğe içerebilir.',
        'file'    => ':attribute en çok :max kilobayt olabilir.',
        'numeric' => ':attribute en çok :max olabilir.',
        'string'  => ':attribute en çok :max karakter olabilir.',
    ],
    'min'                  => [
        'array'   => ':attribute en az :min öğe içermelidir.',
        'file'    => ':attribute en az :min kilobayt olmalıdır.',
        'numeric' => ':attribute en az :min olmalıdır.',
        'string'  => ':attribute en az :min karakter olmalıdır.',
    ],
    'not_in'               => 'Seçilen :attribute geçersiz.',
    'not_regex'            => ':attribute biçimi geçersiz.',
    'numeric'              => ':attribute bir sayı olmalıdır.',
    'present'              => ':attribute gönderilmelidir.',
    'prohibited'           => ':attribute gönderilemez.',
    'regex'                => ':attribute biçimi geçersiz.',
    'required'             => ':attribute alanı zorunludur.',
    'required_if'          => ':other :value olduğunda :attribute zorunludur.',
    'required_unless'      => ':other :values içinde olmadıkça :attribute zorunludur.',
    'required_with'        => ':values gönderildiğinde :attribute zorunludur.',
    'required_with_all'    => ':values gönderildiğinde :attribute zorunludur.',
    'required_without'     => ':values gönderilmediğinde :attribute zorunludur.',
    'required_without_all' => ':values hiçbiri gönderilmediğinde :attribute zorunludur.',
    'same'                 => ':attribute ile :other aynı olmalıdır.',
    'size'                 => [
        'array'   => ':attribute :size öğe içermelidir.',
        'file'    => ':attribute :size kilobayt olmalıdır.',
        'numeric' => ':attribute :size olmalıdır.',
        'string'  => ':attribute :size karakter olmalıdır.',
    ],
    'string'               => ':attribute bir metin olmalıdır.',
    'unique'               => ':attribute zaten kullanılıyor.',
    'url'                  => ':attribute geçerli bir adres olmalıdır.',
    'uuid'                 => ':attribute geçerli bir UUID olmalıdır.',

    'custom' => [],

    /*
    | Alan adları: API gövdesindeki anahtarlar oyuncuya okunur Türkçeyle
    | gösterilir.
    */
    'attributes' => [
        'device'                => 'cihaz',
        'email'                 => 'e-posta',
        'password'              => 'şifre',
        'password_confirmation' => 'şifre tekrarı',
        'name'                  => 'ad',
        'player_name'           => 'oyuncu adı',
        'description'           => 'açıklama',
        'type'                  => 'tür',
        'message'               => 'ileti',
        'body'                  => 'ileti',
        'troops'                => 'askerler',
        'spells'                => 'büyüler',
        'heroes'                => 'alplar',
        'count'                 => 'adet',
        'quantity'              => 'adet',
        'x'                     => 'x konumu',
        'y'                     => 'y konumu',
        'building_id'           => 'bina',
        'troop_key'             => 'asker',
        'clan_id'               => 'boy',
        'player_id'             => 'oyuncu',
        'min_trophies'          => 'en az kupa',
        'min_otag_level'        => 'en az otağ seviyesi',
        'role'                  => 'görev',
        'region'                => 'bölge',
        'page'                  => 'sayfa',
        'per_page'              => 'sayfa başına',
    ],

];
