<?php

return [
    'admin' => [
        'dashboard' => [
            'enabled' => true,
            'position' => 1,
            'title' => [
                'pl' => 'Shoper AI Search',
                'en' => 'Shoper AI Search',
                'ru' => 'Shoper AI Search'
            ],
            'url' => '/admin/dashboard',
            'icon' => 'fa-search',
            'menu' => [
                'enabled' => true,
                'items' => [
                    [
                        'title' => [
                            'pl' => 'Panel główny',
                            'en' => 'Dashboard',
                            'ru' => 'Панель управления'
                        ],
                        'url' => '/admin/dashboard',
                        'icon' => 'fa-home'
                    ],
                    [
                        'title' => [
                            'pl' => 'Ustawienia',
                            'en' => 'Settings',
                            'ru' => 'Настройки'
                        ],
                        'url' => '/admin/settings',
                        'icon' => 'fa-cogs'
                    ]
                ]
            ]
        ]
    ]
];
