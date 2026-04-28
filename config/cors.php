<?php
// config/cors.php
// Ganti dengan konfigurasi ini untuk allow React frontend

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:5173'], // Sesuaikan dengan port Vite

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // Penting untuk Sanctum
];
