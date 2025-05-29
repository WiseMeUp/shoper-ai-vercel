<?php

/**
 * Основной файл конфигурации приложения для Shoper
 * 
 * Содержит основные параметры и хуки для интеграции приложения
 * с платформой Shoper.
 */

use ShoperAI\Model\AdminLink;
use ShoperAI\Service\ShoperApiClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Загрузка конфигурации
$config = require_once __DIR__ . '/config/admin.php';

// Настройки приложения
return [
    // Основные параметры приложения
    'app' => [
        'id' => 'shoper_admin_links', // Уникальный идентификатор приложения
        'version' => '1.0.0', // Версия приложения
        'name' => 'Admin Links Manager', // Название приложения
        'vendor' => 'ShoperAI', // Производитель
        'homepage' => 'https://www.example.com', // Домашняя страница
        'support_email' => 'support@example.com', // Email поддержки
        'min_shoper_version' => '2.0.0', // Минимальная версия Shoper
    ],
    
    // Настройки установки
    'installation' => [
        // Обработчик установки приложения
        'install' => function($params) {
            try {
                // Создаем логгер
                $logger = new Logger('app_installation');
                $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/installation.log', Logger::INFO));
                
                $logger->info('Начало установки приложения', [
                    'shop' => $params['shop'] ?? 'unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                // Создание необходимых директорий
                $directories = [
                    __DIR__ . '/logs',
                    __DIR__ . '/database',
                    __DIR__ . '/tmp'
                ];
                
                foreach ($directories as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                        $logger->info('Создана директория', ['dir' => $dir]);
                    }
                }
                
                // Инициализация базы данных для ссылок
                $linkModel = new AdminLink($logger);
                $linkModel->saveLinks();
                
                // Регистрация прав доступа в Shoper
                if (isset($params['shop']) && isset($params['access_token'])) {
                    $apiClient = new ShoperApiClient($params['shop'], $params['access_token']);
                    
                    // Загрузка конфигурации
                    $config = require_once __DIR__ . '/config/admin.php';
                    
                    // Регистрация модуля прав доступа
                    if (isset($config['permissions']['admin_links'])) {
                        $permission = $config['permissions']['admin_links'];
                        $apiClient->post('admin_permissions', [
                            'name' => $permission['name']['pl'],
                            'description' => $permission['description']['pl'],
                            'app_id' => 'shoper_admin_links',
                            'scopes' => array_keys($permission['scopes'])
                        ]);
                        
                        $logger->info('Зарегистрированы права доступа', [
                            'module' => 'admin_links'
                        ]);
                    }
                }
                
                $logger->info('Установка приложения успешно завершена', [
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Приложение успешно установлено'
                ];
            } catch (\Exception $e) {
                if (isset($logger)) {
                    $logger->error('Ошибка при установке приложения', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                return [
                    'success' => false,
                    'message' => 'Ошибка при установке приложения: ' . $e->getMessage()
                ];
            }
        },
        
        // Обработчик обновления приложения
        'update' => function($params) {
            try {
                // Создаем логгер
                $logger = new Logger('app_update');
                $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/installation.log', Logger::INFO));
                
                $logger->info('Начало обновления приложения', [
                    'shop' => $params['shop'] ?? 'unknown',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'from_version' => $params['from_version'] ?? 'unknown',
                    'to_version' => $params['to_version'] ?? '1.0.0'
                ]);
                
                // Обновление конфигурации в Shoper
                if (isset($params['shop']) && isset($params['access_token'])) {
                    $apiClient = new ShoperApiClient($params['shop'], $params['access_token']);
                    
                    // Обновление регистрации прав доступа
                    $config = require_once __DIR__ . '/config/admin.php';
                    
                    if (isset($config['permissions']['admin_links'])) {
                        $permission = $config['permissions']['admin_links'];
                        $apiClient->put('admin_permissions/shoper_admin_links', [
                            'name' => $permission['name']['pl'],
                            'description' => $permission['description']['pl'],
                            'scopes' => array_keys($permission['scopes'])
                        ]);
                        
                        $logger->info('Обновлены права доступа', [
                            'module' => 'admin_links'
                        ]);
                    }
                }
                
                $logger->info('Обновление приложения успешно завершено', [
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Приложение успешно обновлено'
                ];
            } catch (\Exception $e) {
                if (isset($logger)) {
                    $logger->error('Ошибка при обновлении приложения', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                return [
                    'success' => false,
                    'message' => 'Ошибка при обновлении приложения: ' . $e->getMessage()
                ];
            }
        },
        
        // Обработчик удаления приложения
        'uninstall' => function($params) {
            try {
                // Создаем логгер
                $logger = new Logger('app_uninstallation');
                $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/installation.log', Logger::INFO));
                
                $logger->info('Начало удаления приложения', [
                    'shop' => $params['shop'] ?? 'unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                // Удаление регистрации прав доступа
                if (isset($params['shop']) && isset($params['access_token'])) {
                    $apiClient = new ShoperApiClient($params['shop'], $params['access_token']);
                    
                    // Удаление регистрации модуля прав
                    $apiClient->delete('admin_permissions/shoper_admin_links');
                    
                    $logger->info('Удалены права доступа', [
                        'module' => 'admin_links'
                    ]);
                }
                
                // Сохранение данных перед удалением (опционально)
                if (file_exists(__DIR__ . '/database/admin_links.json')) {
                    $backupDir = __DIR__ . '/database/backups';
                    if (!is_dir($backupDir)) {
                        mkdir($backupDir, 0755, true);
                    }
                    
                    $timestamp = date('Y-m-d_H-i-s');
                    $backupFile = $backupDir . '/admin_links_backup_' . $timestamp . '.json';
                    copy(__DIR__ . '/database/admin_links.json', $backupFile);
                    
                    $logger->info('Создана резервная копия данных', [
                        'file' => $backupFile
                    ]);
                }
                
                $logger->info('Удаление приложения успешно завершено', [
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Приложение успешно удалено'
                ];
            } catch (\Exception $e) {
                if (isset($logger)) {
                    $logger->error('Ошибка при удалении приложения', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                return [
                    'success' => false,
                    'message' => 'Ошибка при удалении приложения: ' . $e->getMessage()
                ];
            }
        }
    ],
    
    // Интеграция с административной панелью
    'admin_panel' => [
        // Настройки меню
        'menu' => $config['menu'],
        
        // Настройки для iframe
        'iframe' => $config['iframe'],
        
        // Настройки прав доступа
        'permissions' => $config['permissions'],
        
        // Обработчики событий административной панели
        'hooks' => [
            // Хук инициализации панели администратора
            'admin_init' => function($params) {
                try {
                    // Загрузка конфигурации
                    $config = require_once __DIR__ . '/config/admin.php';
                    
                    // Создаем логгер
                    $logger = new Logger('admin_panel');
                    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/admin_panel.log', Logger::INFO));
                    
                    // Получаем все зарегистрированные ссылки
                    $links = AdminLink::getAll($logger);
                    
                    // Инициализация клиента API
                    $apiClient = null;
                    if (isset($params['shop']) && isset($params['access_token'])) {
                        $apiClient = new ShoperApiClient($params['shop'], $params['access_token']);
                    }
                    
                    // Регистрация ссылок в меню
                    if ($apiClient) {
                        foreach ($links as $link) {
                            // Проверка прав доступа
                            $hasPermission = true;
                            if (!empty($link['permissions'])) {
                                // Здесь должна быть логика проверки прав доступа
                                // для текущего администратора
                            }
                            
                            if ($hasPermission) {
                                $menuItem = [
                                    'name' => $link['name'],
                                    'url' => $link['url'],
                                    'placement' => $link['placement'],
                                    'icon' => 'fa-link', // Можно сделать настраиваемым
                                    'open_type' => $link['openType']
                                ];
                                
                                // Регистрация пункта меню через API
                                $apiClient->post('admin_menu/items', $menuItem);
                            }
                        }
                    }
                    
                    // Возвращаем настройки для интеграции с админ-панелью
                    return [
                        'success' => true,
                        'menu_items' => count($links),
                        'config' => [
                            'admin_links_enabled' => true,
                            'iframe_settings' => $config['iframe']
                        ]
                    ];
                } catch (\Exception $e) {
                    if (isset($logger)) {
                        $logger->error('Ошибка при инициализации админ-панели', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при инициализации админ-панели: ' . $e->getMessage()
                    ];
                }
            },
            
            // Хук проверки прав доступа
            'check_permissions' => function($params) {
                try {
                    // Создаем логгер
                    $logger = new Logger('permissions');
                    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/permissions.log', Logger::INFO));
                    
                    // Получаем запрашиваемые данные
                    $module = $params['module'] ?? '';
                    $scope = $params['scope'] ?? '';
                    $adminId = $params['admin_id'] ?? 0;
                    
                    // Проверка прав доступа для модуля admin_links
                    if ($module === 'admin_links') {
                        // Здесь должна быть логика проверки прав доступа
                        // для конкретного администратора и области действия
                        
                        // Возвращаем результат проверки
                        return [
                            'success' => true,
                            'has_permission' => true, // Или false в зависимости от проверки
                            'module' => $module,
                            'scope' => $scope
                        ];
                    }
                    
                    // Для других модулей возвращаем null (не обрабатываем)
                    return null;
                } catch (\Exception $e) {
                    if (isset($logger)) {
                        $logger->error('Ошибка при проверке прав доступа', [
                            'message' => $e->getMessage(),
                            'params' => $params
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при проверке прав доступа: ' . $e->getMessage()
                    ];
                }
            }
        ]
    ],
    
    // Публичный API для интеграции с фронтендом
    'api' => [
        'routes' => [
            // Получение списка ссылок
            'GET:/api/admin-links' => function($request, $response) {
                try {
                    // Создаем логгер
                    $logger = new Logger('api');
                    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/api.log', Logger::INFO));
                    
                    // Проверка прав доступа
                    if (!isset($request['admin_id']) || !isset($request['access_token'])) {
                        return [
                            'success' => false,
                            'message' => 'Unauthorized access',
                            'status' => 401
                        ];
                    }
                    
                    // Получаем все ссылки
                    $links = AdminLink::getAll($logger);
                    
                    return [
                        'success' => true,
                        'data' => $links,
                        'count' => count($links)
                    ];
                } catch (\Exception $e) {
                    if (isset($logger)) {
                        $logger->error('Ошибка при получении списка ссылок', [
                            'message' => $e->getMessage()
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при получении списка ссылок: ' . $e->getMessage(),
                        'status' => 500
                    ];
                }
            },
            
            // Создание новой ссылки
            'POST:/api/admin-links' => function($request, $response) {
                try {
                    // Создаем логгер
                    $logger = new Logger('api');
                    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/api.log', Logger::INFO));
                    
                    // Проверка прав доступа
                    if (!isset($request['admin_id']) || !isset($request['access_token'])) {
                        return [
                            'success' => false,
                            'message' => 'Unauthorized access',
                            'status' => 401
                        ];
                    }
                    
                    // Проверка наличия данных
                    if (!isset($request['data']) || !is_array($request['data'])) {
                        return [
                            'success' => false,
                            'message' => 'Invalid data',
                            'status' => 400
                        ];
                    }
                    
                    // Создаем новую ссылку
                    $linkModel = new AdminLink($logger);
                    
                    // Валидация данных
                    $validation = $linkModel->validate($request['data']);
                    if (!$validation['success']) {
                        return [
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validation['errors'],
                            'status' => 400
                        ];
                    }
                    
                    // Устанавливаем данные и сохраняем
                    $linkModel->setFromArray($request['data']);
                    $result = $linkModel->save();
                    
                    if ($result) {
                        return [
                            'success' => true,
                            'message' => 'Link created successfully',
                            'data' => $linkModel->toArray()
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to save link',
                            'status' => 500
                        ];
                    }
                } catch (\Exception $e) {
                    if (isset($logger)) {
                        $logger->error('Ошибка при создании ссылки', [
                            'message' => $e->getMessage(),
                            'data' => $request['data'] ?? []
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при создании ссылки: ' . $e->getMessage(),
                        'status' => 500
                    ];
                }
            },
            
            // Обновление существующей ссылки
            'PUT:/api/admin-links/{id}' => function($request, $response) {
                try {
                    // Создаем логгер
                    $logger = new Logger('api');
                    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/api.log', Logger::INFO));
                    
                    // Проверка прав доступа
                    if (!isset($request['admin_id']) || !isset($request['access_token'])) {
                        return [
                            'success' => false,
                            'message' => 'Unauthorized access',
                            'status' => 401
                        ];
                    }
                    
                    // Проверка наличия данных
                    if (!isset($request['data']) || !is_array($request['data']) || !isset($request['params']['id'])) {
                        return [
                            'success' => false,
                            'message' => 'Invalid data or missing ID',
                            'status' => 400
                        ];
                    }
                    
                    // Получаем ссылку по ID
                    $linkModel = AdminLink::findById($request['params']['id'], $logger);
                    if (!$linkModel) {
                        return [
                            'success' => false,
                            'message' => 'Link not found',
                            'status' => 404
                        ];
                    }
                    
                    // Валидация данных
                    $validation = $linkModel->validate($request['data']);
                    if (!$validation['success']) {
                        return [
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => $validation['errors'],
                            'status' => 400
                        ];
                    }
                    
                    // Обновляем данные и сохраняем
                    $linkModel->setFromArray($request['data']);
                    $result = $linkModel->save();
                    
                    if ($result) {
                        return [
                            'success' => true,
                            'message' => 'Link updated successfully',
                            'data' => $linkModel->toArray()
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to update link',
                            'status' => 500
                        ];
                    }
                } catch (\Exception $e) {
                    if (isset($logger)) {
                        $logger->error('Ошибка при обновлении ссылки', [
                            'message' => $e->getMessage(),
                            'id' => $request['params']['id'] ?? '',
                            'data' => $request['data'] ?? []
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при обновлении ссылки: ' . $e->getMessage(),
                        'status' => 500
                    ];
                }
            },
            
            // Удаление ссылки
            'DELETE:/api/admin-links/{id}' => function($request, $response) {
                try {
                    // Создаем логгер
                    $logger = new Logger('api');
                    $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/api.log', Logger::INFO));
                    
                    // Проверка прав доступа
                    if (!isset($request['admin_id']) || !isset($request['access_token'])) {
                        return [
                            'success' => false,
                            'message' => 'Unauthorized access',
                            'status' => 401
                        ];
                    }
                    
                    // Проверка наличия ID
                    if (!isset($request['params']['id'])) {
                        return [
                            'success' => false,
                            'message' => 'Missing ID',
                            'status' => 400
                        ];
                    }
                    
                    // Получаем ссылку для проверки существования
                    $linkModel = AdminLink::findById($request['params']['id'], $logger);
                    if (!$linkModel) {
                        return [
                            'success' => false,
                            'message' => 'Link not found',
                            'status' => 404
                        ];
                    }
                    
                    // Удаляем ссылку
                    $result = $linkModel->delete($request['params']['id']);
                    
                    if ($result) {
                        return [
                            'success' => true,
                            'message' => 'Link deleted successfully'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to delete link',
                            'status' => 500
                        ];
                    }
                } catch (\Exception $e) {
                    if (isset($logger)) {
                        $logger->error('Ошибка при удалении ссылки', [
                            'message' => $e->getMessage(),
                            'id' => $request['params']['id'] ?? ''
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при удалении ссылки: ' . $e->getMessage(),
                        'status' => 500
                    ];
                }
            }
        ]
    ]
];

