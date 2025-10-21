<?php

return [
    'paths' => ['api/*', 'auth/*', 'login', 'logout', 'user', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // Cho domain nào được gọi API (thêm domain FE thật của bạn nếu có)
    'allowed_origins' => [
        'http://localhost:5173',
        'https://localhost:5173',
        // 'https://app.yourdomain.com',
    ],
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Bearer token => KHÔNG dùng cookie
    'supports_credentials' => false,
];
