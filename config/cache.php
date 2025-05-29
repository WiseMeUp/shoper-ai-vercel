<?php

/**
 * Конфигурационный файл для системы кэширования
 */

return [
    // Основные настройки
    'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
    
    // Настройки для разных драйверов кэширования
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => $_ENV['CACHE_FILE_PATH'] ?? __DIR__ . '/../var/cache',
            'permissions' => 0755,
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? 0,
            'prefix' => $_ENV['CACHE_PREFIX'] ?? 'shoper_cache:',
        ],
    ],
    
    // Время жизни кэша по умолчанию (в секундах)
    'ttl' => 3600,
    
    // Префикс для ключей кэша
    'prefix' => $_ENV['CACHE_PREFIX'] ?? 'shoper_cache:',

