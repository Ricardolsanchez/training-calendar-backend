<?php

return [

    // 👉 Para desarrollo: aplica CORS a TODAS las rutas
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 👉 Necesario para cookies de Sanctum
    'supports_credentials' => true,
]; 