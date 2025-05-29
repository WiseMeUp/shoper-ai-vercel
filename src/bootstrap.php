<?php

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$config = [
    'appId' => $_ENV['SHOPER_APP_ID'],
    'appSecret' => $_ENV['SHOPER_APP_SECRET'],
    'appstoreSecret' => $_ENV['SHOPER_APPSTORE_SECRET'],
    'db' => [
        'connection' => 'mysql:host=' . ($_ENV['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'app'),
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASSWORD'] ?? ''
    ]
];

return $config;
