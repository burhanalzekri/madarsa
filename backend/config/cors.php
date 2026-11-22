<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        explode(',', env('FRONTEND_URL', 'http://127.0.0.1:5173')),
        ['http://127.0.0.1:5173', 'http://localhost:5173']
    )))),
    'allowed_origins_patterns' => [
        '#^https?://(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2[0-9]|3[0-1])\.\d{1,3}\.\d{1,3}):5173$#',
        '#^https://[a-z0-9-]+\.vercel\.app$#i',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
