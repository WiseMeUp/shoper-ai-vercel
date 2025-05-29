<?php

/**
 * Конфигурационный файл для поисковой системы
 */

return [
    // Основные настройки
    'driver' => $_ENV['SEARCH_DRIVER'] ?? 'elasticsearch', // elasticsearch или trieve

    // Настройки для SmartSearch
    'smart_search' => [
        'enabled' => true,
        
        // Распознавание параметров в запросе
        'parameter_recognition' => [
            'enabled' => true,
            'parameters' => [
                'brand' => [
                    'patterns' => ['nike', 'adidas', 'puma', 'reebok', 'new balance', 'asics'],
                    'prefix' => ['бренд', 'фирма', 'марка'],
                ],
                'color' => [
                    'patterns' => ['белый', 'черный', 'красный', 'синий', 'зеленый', 'желтый', 'серый', 'розовый', 'оранжевый', 'фиолетовый', 'коричневый'],
                    'prefix' => ['цвет', 'цвета'],
                ],
                'size' => [
                    'patterns' => ['/\b\d+\s*размер\b/i', '/\bразмер\s*\d+\b/i'],
                    'is_regex' => true,
                ],
                'price' => [
                    'patterns' => ['/\bдо\s*(\d+)\s*р\b/i', '/\bот\s*(\d+)\s*р\b/i', '/\b(\d+)-(\d+)\s*р\b/i'],
                    'is_regex' => true,
                ],
            ],
        ],
        
        // Настройки ранжирования
        'ranking' => [
            'popularity_weight' => 0.3,
            'stock_weight' => 0.2,
            'relevancy_weight' => 0.5,
        ],
        
        // Динамические подсказки
        'suggestions' => [
            'enabled' => true,
            'max_suggestions' => 5,
            'min_query_length' => 3,
            'cache_ttl' => 3600, // 1 час
        ],
    ],
    
    // Кэширование результатов поиска
    'cache' => [
        'enabled' => true,
        'ttl' => 1800, // 30 минут
        'driver' => 'redis', // redis или file
    ],
    
    // Настройки для пользовательского интерфейса
    'ui' => [
        'results_per_page' => 6,
        'instant_search' => [
            'enabled' => true,
            'delay' => 300, // задержка в миллисекундах
        ],
    ],
];

