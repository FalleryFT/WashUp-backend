<?php
// config/cors.php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    // ⚠️ Tambah Content-Disposition agar browser bisa trigger download
    'allowed_headers' => ['*'],

    // ✅ Expose Content-Disposition agar axios bisa baca nama file
    'exposed_headers' => [
        'Content-Disposition',
        'Content-Type',
        'Content-Length',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];