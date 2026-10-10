<?php

/*
|--------------------------------------------------------------------------
| CORS — Unity istemcisi için
|--------------------------------------------------------------------------
|
| Unity Editor ve mobil derlemeler CORS'tan etkilenmez; ancak WebGL
| derlemesi tarayıcıda çalıştığı için bu ayarlar gereklidir.
| Üretimde GAME_ALLOWED_ORIGINS'e kendi alan adınızı yazın.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('GAME_ALLOWED_ORIGINS', '*'))
)));

return [
    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins ?: ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['ETag'],

    'max_age' => 3600,

    'supports_credentials' => false,
];
