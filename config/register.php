<?php

return [
    'admin' => [
        'name' => 'Shoper AI Search',
        'vendor' => 'ShoperAI',
        'version' => '1.0.0',
        'min_version' => '2.0.0',
        'type' => 'integration',
        'hidden' => false,
        'main_page' => [
            'url' => '/admin/dashboard',
            'title' => [
                'pl' => 'Panel główny',
                'en' => 'Dashboard',
                'ru' => 'Панель управления'
            ]
        ],
        'menu' => [
            'placement' => 'left',
            'position' => 100,
            'icon' => 'fa-search',
            'items' => [
                [
                    'url' => '/admin/dashboard',
                    'title' => [
                        'pl' => 'Panel główny',
                        'en' => 'Dashboard',
                        'ru' => 'Панель управления'
                    ]
                ],
                [
                    'url' => '/admin/settings',
                    'title' => [
                        'pl' => 'Ustawienia',
                        'en' => 'Settings',
                        'ru' => 'Настройки'
                    ]
                ]
            ]
        ],
        'privileges' => [
            'shoper_ai_search' => [
                'description' => [
                    'pl' => 'Dostęp do Shoper AI Search',
                    'en' => 'Access to Shoper AI Search',
                    'ru' => 'Доступ к Shoper AI Search'
                ]
            ]
        ]
    ]
];
