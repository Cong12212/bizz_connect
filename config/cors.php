<?php

return [
    'paths' => ['api/*', 'auth/*', 'login', 'logout', 'user', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // ✅ Cho phép tất cả localhost bằng pattern
    'allowed_origins' => [
        'https://bizz-connect-web.onrender.com',
        'https://biz-connect.online',
    ],
    'allowed_origins_patterns' => [
        '/^http:\/\/localhost:\d+$/',
        '/^http:\/\/127\.0\.0\.1:\d+$/',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Bearer token => KHÔNG dùng cookie
    'supports_credentials' => false,
];
