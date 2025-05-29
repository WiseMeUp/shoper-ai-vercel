<?php

/**
 * Конфигурационный файл для административной панели Shoper
 * 
 * Содержит настройки для интеграции плагина управления ссылками
 * в панель администратора Shoper.
 */

return [
// Основные настройки приложения
    'app' => [
        'name' => [
            'pl' => 'Shoper AI Search',
            'en' => 'Shoper AI Search',
            'ru' => 'Shoper AI Search'
        ],
        'description' => [
            'pl' => 'Zaawansowane wyszukiwanie AI dla sklepu',
            'en' => 'Advanced AI search for your store',
            'ru' => 'Продвинутый AI-поиск для магазина'
        ],
        'version' => '1.0.0',
        'author' => 'ShoperAI',
        'website' => 'https://www.example.com',
        'icon' => 'fa-robot'
    ],
    
// Настройки отображения в меню
    'menu' => [
        'enabled' => true,
        'position' => 100, // Позиция в меню
        'parent' => null, // Корневое меню
        'icon' => 'fa-search',
        'title' => [
            'pl' => 'Shoper AI Search',
            'en' => 'Shoper AI Search',
            'ru' => 'Shoper AI Search'
        ]
    ],
    
// Настройки прав доступа
    'permissions' => [
        'shoper_ai' => [
            'name' => [
                'pl' => 'Wyszukiwarka AI',
                'en' => 'AI Search',
                'ru' => 'AI Поиск'
            ],
            'description' => [
                'pl' => 'Dostęp do zaawansowanej wyszukiwarki AI',
                'en' => 'Access to advanced AI search features',
                'ru' => 'Доступ к расширенным функциям AI-поиска'
            ],
            'scopes' => [
                'admins' => [
                    'pl' => 'Administratorzy (odczyt)',
                    'en' => 'Admins (read)',
                    'ru' => 'Администраторы (чтение)'
                ],
                'dashboard' => [
                    'pl' => 'Pulpit (odczyt)',
                    'en' => 'Dashboard (read)',
                    'ru' => 'Панель управления (чтение)'
                ],
                'additional_fields' => [
                    'pl' => 'Pola dodatkowe formularzy (odczyt)',
                    'en' => 'Additional form fields (read)',
                    'ru' => 'Дополнительные поля форм (чтение)'
                ],
                'shop_config' => [
                    'pl' => 'Konfiguracja sklepu (odczyt)',
                    'en' => 'Shop configuration (read)',
                    'ru' => 'Конфигурация магазина (чтение)'
                ],
                'progress_manager' => [
                    'pl' => 'Menadżer postępu (odczyt)',
                    'en' => 'Progress manager (read)',
                    'ru' => 'Менеджер прогресса (чтение)'
                ]
            ]
        ]
    ],
    
    // API настройки
    'api' => [
        'endpoints' => [
            'search' => [
                'pl' => 'Wyszukiwanie',
                'en' => 'Search',
                'ru' => 'Поиск'
            ],
            'config' => [
                'pl' => 'Konfiguracja',
                'en' => 'Configuration',
                'ru' => 'Конфигурация'
            ]
        ],
        
        // Доступные действия
        'actions' => [
            'search' => [
                'pl' => 'Wyszukaj',
                'en' => 'Search',
                'ru' => 'Поиск'
            ],
            'analyze' => [
                'pl' => 'Analiza AI',
                'en' => 'AI Analysis',
                'ru' => 'AI Анализ'
            ]
        ]
    ],
    
    // Настройки для iframe
    'iframe' => [
        'default_width' => '100%',
        'default_height' => '800px',
        'allow_scripts' => true,
        'allow_same_origin' => true,
        'allow_forms' => true
    ],
    
    // Настройки для URL
    'url' => [
        'allowed_domains' => ['*'], // Разрешить все домены для URL в iframe
        'validate_ssl' => true, // Проверять SSL-сертификаты
        'timeout' => 10 // Таймаут для запросов в секундах
    ],
    
    // Настройки AI
    'ai' => [
        'provider' => 'trieve',
        'api_endpoint' => 'https://api.trieve.ai/api/v1',
        'features' => [
            'search' => [
                'pl' => 'Wyszukiwanie AI',
                'en' => 'AI Search',
                'ru' => 'AI Поиск'
            ],
            'analytics' => [
                'pl' => 'Analityka wyszukiwania',
                'en' => 'Search Analytics',
                'ru' => 'Аналитика поиска'
            ]
        ],
        'settings' => [
            'chunk_size' => 1000,
            'overlap' => 200,
            'max_tokens' => 2048,
            'webhooks' => [
                'enabled' => true,
                'link_config' => [
                    'title' => [
                        'pl' => 'Konfiguracja',
                        'en' => 'Configuration',
                        'ru' => 'Конфигурация'
                    ],
                    'location' => 'Konfiguracja / Nagłówek - akcje'
                ],
                // Отключаем вебхуки для действий с товарами
                'product_actions' => [
                    'delete' => false,
                    'edit' => false,
                    'create' => false
                ]
            ]
        ]
    ]
];

