<?php

/**
 * Конфигурационный файл для ElasticSearch
 */

return [
    // Основные настройки подключения
    'connections' => [
        'default' => [
            'hosts' => explode(',', $_ENV['ELASTICSEARCH_HOSTS'] ?? 'localhost:9200'),
            'username' => $_ENV['ELASTICSEARCH_USERNAME'] ?? null,
            'password' => $_ENV['ELASTICSEARCH_PASSWORD'] ?? null,
            'ssl' => [
                'enabled' => filter_var($_ENV['ELASTICSEARCH_SSL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'verify' => filter_var($_ENV['ELASTICSEARCH_SSL_VERIFY'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'timeout' => (int)($_ENV['ELASTICSEARCH_TIMEOUT'] ?? 30),
        ],
    ],

    // Настройки индексов
    'indices' => [
        'products' => [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'refresh_interval' => '1s',
                'analysis' => [
                    'analyzer' => [
                        'polish_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => [
                                'lowercase',
                                'stop',
                                'trim',
                                'synonym',
                                'asciifolding'
                            ],
                        ],
                    ],
                    'filter' => [
                        'synonym' => [
                            'type' => 'synonym',
                            'synonyms' => [
                                'buty sportowe, sneakers, obuwie sportowe',
                                'kurtka, płaszcz, jacket',
                                'spodnie, pants, jeansy',
                            ],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'properties' => [
                    'product_id' => ['type' => 'keyword'],
                    'name' => [
                        'type' => 'text',
                        'analyzer' => 'polish_analyzer',
                        'fields' => [
                            'keyword' => ['type' => 'keyword'],
                        ],
                    ],
                    'description' => [
                        'type' => 'text',
                        'analyzer' => 'polish_analyzer'
                    ],
                    'price' => ['type' => 'double'],
                    'price_range' => ['type' => 'keyword'],
                    'brand' => [
                        'type' => 'text',
                        'fields' => [
                            'keyword' => ['type' => 'keyword']
                        ]
                    ],
                    'categories' => [
                        'type' => 'text',
                        'fields' => [
                            'keyword' => ['type' => 'keyword']
                        ]
                    ],
                    'tags' => [
                        'type' => 'text',
                        'fields' => [
                            'keyword' => ['type' => 'keyword']
                        ]
                    ],
                    'attributes' => [
                        'type' => 'nested',
                        'properties' => [
                            'name' => ['type' => 'keyword'],
                            'value' => ['type' => 'keyword']
                        ]
                    ],
                    'colors' => ['type' => 'keyword'],
                    'sizes' => ['type' => 'keyword'],
                    'stock_status' => ['type' => 'keyword'],
                    'stock' => ['type' => 'integer'],
                    'popularity_score' => ['type' => 'float'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                    'sku' => ['type' => 'keyword'],
                    'tax_id' => ['type' => 'integer'],
                    'currency' => ['type' => 'keyword'],
                    'manufacturer_id' => ['type' => 'integer'],
                    'unit' => ['type' => 'keyword'],
                    'stock_weight' => ['type' => 'float'],
                    'search_data' => [
                        'type' => 'text',
                        'analyzer' => 'polish_analyzer'
                    ]
                ],
            ],
        ],
    ],
    
    // Настройки поиска
    'search' => [
        'result_limit' => 20,
        'min_score' => 0.2,
        'field_weights' => [
            'name' => 3.0,
            'brand' => 2.0,
            'description' => 1.0,
            'categories' => 1.5,
            'tags' => 1.0,
        ],
    ],
    
    // Настройки кэширования
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 час
        'prefix' => 'es_cache:',
        'popular_queries' => [
            'enabled' => true,
            'ttl' => 86400, // 24 часа
            'min_hits' => 10
        ]
    ],
    
    // Настройки умного поиска
    'smart_search' => [
        'parameter_recognition' => [
            'enabled' => true,
            'parameters' => [
                'brand' => [
                    'is_regex' => false,
                    'prefix' => ['marka', 'brand', 'firma'],
                    'patterns' => []
                ],
                'color' => [
                    'is_regex' => false,
                    'prefix' => ['kolor', 'color'],
                    'patterns' => [
                        'biały', 'czarny', 'czerwony', 'niebieski', 'zielony',
                        'żółty', 'różowy', 'fioletowy', 'brązowy', 'szary'
                    ]
                ],
                'size' => [
                    'is_regex' => true,
                    'prefix' => ['rozmiar', 'size'],
                    'patterns' => [
                        '/rozmiar[:\s]+(\d+)/',
                        '/size[:\s]+(\d+)/',
                        '/(\d+)\s*(?:eu|cm|mm)/'
                    ]
                ],
                'price' => [
                    'is_regex' => true,
                    'prefix' => ['cena', 'price'],
                    'patterns' => [
                        '/do\s*(\d+)\s*zł/',
                        '/od\s*(\d+)\s*zł/',
                        '/(\d+)\s*-\s*(\d+)\s*zł/',
                        '/(\d+)\s*zł/',
                        '/(\d+)\s*PLN/i'
                    ]
                ]
            ]
        ],
        
        'ranking' => [
            'factors' => [
                'exact_name_match' => 3.0,
                'partial_name_match' => 2.0,
                'brand_match' => 1.5,
                'description_match' => 1.0,
                'tags_match' => 1.0,
                'popularity' => 1.2,
                'stock_status' => 1.1
            ],
            'min_score' => 0.2
        ],
        
        'suggestions' => [
            'enabled' => true,
            'max_suggestions' => 5,
            'min_word_length' => 3,
            'types' => [
                'category' => true,
                'brand' => true,
                'product' => true,
                'popular_queries' => true
            ]
        ]
    ],
    
    // Настройки индексации
    'indexing' => [
        'bulk_size' => 500,
        'queue' => [
            'enabled' => true,
            'connection' => 'redis',
            'queue' => 'elasticsearch'
        ],
        'refresh_interval' => '1s'
    ],
    
    // Настройки логирования
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'slow_query_threshold' => 5.0 // секунды
    ]
];

