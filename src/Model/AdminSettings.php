<?php

namespace ShoperAI\Model;

use Monolog\Logger;
use ShoperAI\Service\ShoperApiClient;

/**
 * Класс AdminSettings для управления настройками приложения
 * 
 * Обеспечивает методы для безопасного хранения, загрузки, валидации
 * и управления настройками приложения, включая резервное копирование.
 */
class AdminSettings
{
    /**
     * @var string Путь к файлу настроек
     */
    private string $settingsPath;
    
    /**
     * @var string Путь к директории с резервными копиями
     */
    private string $backupDir;
    
    /**
     * @var array Настройки по умолчанию
     */
    private array $defaultSettings = [
        'api_key' => '',
        'api_endpoint' => 'https://api.openai.com/v1',
        'search_enabled' => false,
        'result_limit' => 10,
        'model_name' => 'gpt-4',
        'temperature' => 0.7,
        'replace_default_search' => true,
        'include_descriptions' => true,
        'include_attributes' => true,
        'created_at' => null,
        'updated_at' => null,
    ];
    
    /**
     * @var array Текущие настройки
     */
    private array $settings = [];
    
    /**
     * @var Logger|null Логгер
     */
    private ?Logger $logger;
    
    /**
     * @var ShoperApiClient|null Клиент API Shoper
     */
    private ?ShoperApiClient $apiClient;
    
    /**
     * Конструктор
     * 
     * @param Logger|null $logger Логгер (опционально)
     * @param ShoperApiClient|null $apiClient Клиент API Shoper (опционально)
     */
    public function __construct(?Logger $logger = null, ?ShoperApiClient $apiClient = null)
    {
        $basePath = realpath(__DIR__ . '/../../');
        $this->settingsPath = $basePath . '/database/settings.json';
        $this->backupDir = $basePath . '/database/backups';
        
        // Создание директорий, если они не существуют
        if (!is_dir(dirname($this->settingsPath))) {
            mkdir(dirname($this->settingsPath), 0755, true);
        }
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        
        // Загрузка настроек при создании объекта
        $this->loadSettings();
    }
    
    /**
     * Загружает настройки из файла
     * 
     * @return array Загруженные настройки
     */
    public function loadSettings(): array
    {
        if (file_exists($this->settingsPath)) {
            $content = file_get_contents($this->settingsPath);
            if ($content) {
                try {
                    $settings = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    
                    // Проверка на корректность структуры
                    if (is_array($settings)) {
                        $this->settings = array_merge($this->defaultSettings, $settings);
                        return $this->settings;
                    }
                } catch (\JsonException $e) {
                    if ($this->logger) {
                        $this->logger->error('Ошибка при чтении файла настроек', [
                            'message' => $e->getMessage(),
                            'file' => $this->settingsPath
                        ]);
                    }
                }
            }
        }
        
        // Если файл не существует или некорректен, используем настройки по умолчанию
        $this->settings = $this->defaultSettings;
        $this->settings['created_at'] = date('Y-m-d H:i:s');
        $this->settings['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->settings;
    }
    
    /**
     * Получает все текущие настройки
     * 
     * @return array Текущие настройки
     */
    public function getAll(): array
    {
        return $this->settings;
    }
    
    /**
     * Получает значение конкретной настройки
     * 
     * @param string $key Ключ настройки
     * @param mixed $default Значение по умолчанию, если настройка не найдена
     * @return mixed Значение настройки или значение по умолчанию
     */
    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Устанавливает значение конкретной настройки
     * 
     * @param string $key Ключ настройки
     * @param mixed $value Значение настройки
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function set(string $key, $value): self
    {
        $this->settings[$key] = $value;
        $this->settings['updated_at'] = date('Y-m-d H:i:s');
        return $this;
    }
    
    /**
     * Сохраняет настройки в файл
     * 
     * @param array|null $data Данные для сохранения (опционально)
     * @return bool Результат операции
     */
    public function save(?array $data = null): bool
    {
        if ($data !== null) {
            // Если переданы данные, обновляем только переданные значения
            foreach ($data as $key => $value) {
                if (array_key_exists($key, $this->defaultSettings)) {
                    $this->settings[$key] = $value;
                }
            }
        }
        
        $this->settings['updated_at'] = date('Y-m-d H:i:s');
        
        try {
            $jsonData = json_encode($this->settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $result = file_put_contents($this->settingsPath, $jsonData, LOCK_EX);
            
            if ($result === false) {
                if ($this->logger) {
                    $this->logger->error('Не удалось сохранить настройки', [
                        'file' => $this->settingsPath
                    ]);
                }
                return false;
            }
            
            // Дополнительно проверяем, что файл был корректно записан
            clearstatcache();
            if (!file_exists($this->settingsPath) || filesize($this->settingsPath) === 0) {
                if ($this->logger) {
                    $this->logger->error('Файл настроек пуст после сохранения', [
                        'file' => $this->settingsPath
                    ]);
                }
                return false;
            }
            
            if ($this->logger) {
                $this->logger->info('Настройки успешно сохранены', [
                    'file' => $this->settingsPath,
                    'size' => filesize($this->settingsPath)
                ]);
            }
            
            return true;
        } catch (\JsonException $e) {
            if ($this->logger) {
                $this->logger->error('Ошибка при сохранении настроек', [
                    'message' => $e->getMessage(),
                    'file' => $this->settingsPath
                ]);
            }
            return false;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Непредвиденная ошибка при сохранении настроек', [
                    'message' => $e->getMessage(),
                    'file' => $this->settingsPath
                ]);
            }
            return false;
        }
    }
    
    /**
     * Валидирует данные настроек
     * 
     * @param array $data Данные для валидации
     * @return array Массив с результатами валидации ['success' => bool, 'errors' => array]
     */
    public function validate(array $data): array
    {
        $errors = [];
        
        // Проверка API ключа
        if (isset($data['api_key']) && empty(trim($data['api_key']))) {
            $errors[] = 'API ключ не может быть пустым';
        }
        
        // Проверка URL API
        if (isset($data['api_endpoint'])) {
            $apiEndpoint = filter_var(trim($data['api_endpoint']), FILTER_VALIDATE_URL);
            if (!$apiEndpoint) {
                $errors[] = 'Endpoint API должен быть корректным URL';
            }
        }
        
        // Проверка лимита результатов
        if (isset($data['result_limit'])) {
            $resultLimit = (int) $data['result_limit'];
            if ($resultLimit < 1 || $resultLimit > 100) {
                $errors[] = 'Лимит результатов должен быть от 1 до 100';
            }
        }
        
        // Проверка температуры
        if (isset($data['temperature'])) {
            $temperature = (float) $data['temperature'];
            if ($temperature < 0 || $temperature > 1) {
                $errors[] = 'Температура должна быть от 0 до 1';
            }
        }
        
        // Проверка модели
        if (isset($data['model_name']) && !in_array($data['model_name'], ['gpt-4', 'gpt-3.5-turbo'])) {
            $errors[] = 'Неподдерживаемая модель AI';
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Создает резервную копию текущих настроек
     * 
     * @return array Информация о созданной резервной копии или ошибке
     */
    public function createBackup(): array
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "settings_backup_{$timestamp}.json";
            $backupPath = $this->backupDir . '/' . $filename;
            
            // Создание директории для бэкапов, если не существует
            if (!is_dir($this->backupDir)) {
                mkdir($this->backupDir, 0755, true);
            }
            
            // Создание копии текущих настроек
            $jsonData = json_encode($this->settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $result = file_put_contents($backupPath, $jsonData, LOCK_EX);
            
            if ($result === false) {
                if ($this->logger) {
                    $this->logger->error('Не удалось создать резервную копию', [
                        'file' => $backupPath
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Не удалось записать файл резервной копии'
                ];
            }
            
            // Логирование успешного создания бэкапа
            if ($this->logger) {
                $this->logger->info('Резервная копия настроек создана', [
                    'file' => $backupPath,
                    'size' => filesize($backupPath)
                ]);
            }
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupPath,
                'size' => $this->formatSize(filesize($backupPath)),
                'date' => $timestamp
            ];
        } catch (\JsonException $e) {
            if ($this->logger) {
                $this->logger->error('Ошибка при создании резервной копии', [
                    'message' => $e->getMessage()
                ]);
            }
            return [
                'success' => false,
                'error' => 'Ошибка при кодировании данных: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Непредвиденная ошибка при создании резервной копии', [
                    'message' => $e->getMessage()
                ]);
            }
            return [
                'success' => false,
                'error' => 'Ошибка: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Восстанавливает настройки из резервной копии
     * 
     * @param string $filename Имя файла резервной копии
     * @return array Результат восстановления
     */
    public function restoreFromBackup(string $filename): array
    {
        try {
            // Безопасное получение имени файла без пути
            $filename = basename($filename);
            $backupPath = $this->backupDir . '/' . $filename;
            
            // Проверка существования файла
            if (!file_exists($backupPath)) {
                if ($this->logger) {
                    $this->logger->error('Файл резервной копии не найден', [
                        'file' => $backupPath
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Файл резервной копии не найден'
                ];
            }
            
            // Создание бэкапа текущих настроек перед восстановлением
            $currentBackup = $this->createBackup();
            if (!$currentBackup['success']) {
                if ($this->logger) {
                    $this->logger->warning('Не удалось создать резервную копию текущих настроек перед восстановлением', [
                        'error' => $currentBackup['error']
                    ]);
                }
            }
            
            // Чтение данных из файла резервной копии
            $content = file_get_contents($backupPath);
            if ($content === false) {
                if ($this->logger) {
                    $this->logger->error('Не удалось прочитать файл резервной копии', [
                        'file' => $backupPath
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Не удалось прочитать файл резервной копии'
                ];
            }
            
            // Декодирование JSON-данных
            $settings = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            
            // Проверка структуры данных
            if (!is_array($settings)) {
                if ($this->logger) {
                    $this->logger->error('Некорректная структура данных в файле резервной копии', [
                        'file' => $backupPath
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Некорректная структура данных в файле резервной копии'
                ];
            }
            
            // Обновление текущих настроек
            $this->settings = array_merge($this->defaultSettings, $settings);
            
            // Обновление временной метки
            $this->settings['updated_at'] = date('Y-m-d H:i:s');
            
            // Сохранение восстановленных настроек
            if (!$this->save()) {
                if ($this->logger) {
                    $this->logger->error('Не удалось сохранить восстановленные настройки', [
                        'file' => $this->settingsPath
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Не удалось сохранить восстановленные настройки'
                ];
            }
            
            // Логирование успешного восстановления
            if ($this->logger) {
                $this->logger->info('Настройки успешно восстановлены из резервной копии', [
                    'backup_file' => $backupPath,
                    'settings_file' => $this->settingsPath
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Настройки успешно восстановлены',
                'backup_created' => $currentBackup['success'] ?? false
            ];
        } catch (\JsonException $e) {
            if ($this->logger) {
                $this->logger->error('Ошибка при восстановлении из резервной копии', [
                    'message' => $e->getMessage(),
                    'file' => $backupPath ?? 'не указан'
                ]);
            }
            return [
                'success' => false,
                'error' => 'Ошибка при декодировании данных: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Непредвиденная ошибка при восстановлении из резервной копии', [
                    'message' => $e->getMessage(),
                    'file' => $backupPath ?? 'не указан'
                ]);
            }
            return [
                'success' => false,
                'error' => 'Ошибка: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Получает список доступных резервных копий
     * 
     * @return array Список резервных копий с метаданными
     */
    public function getBackupsList(): array
    {
        $backups = [];
        
        if (!is_dir($this->backupDir)) {
            return $backups;
        }
        
        $files = glob($this->backupDir . '/settings_backup_*.json');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $timestamp = filemtime($file);
            $size = filesize($file);
            
            // Извлечение даты из имени файла
            preg_match('/settings_backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.json/', $filename, $matches);
            $dateStr = $matches[1] ?? date('Y-m-d H:i:s', $timestamp);
            
            $backups[] = [
                'filename' => $filename,
                'path' => $file,
                'date' => str_replace('_', ' ', $dateStr),
                'timestamp' => $timestamp,
                'size' => $this->formatSize($size)
            ];
        }
        
        // Сортировка по времени создания (новые вначале)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return $backups;
    }
    
    /**
     * Форматирует размер файла в человеко-читаемый вид
     * 
     * @param int $bytes Размер в байтах
     * @param int $precision Точность
     * @return string Отформатированный размер
     */
    private function formatSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Синхронизирует настройки с Shoper API
     * 
     * @param string $shopUrl URL магазина
     * @return array Результат синхронизации
     */
    public function syncWithShoper(string $shopUrl): array
    {
        if (!$this->apiClient) {
            if ($this->logger) {
                $this->logger->warning('Невозможно синхронизировать настройки с Shoper: API клиент не настроен');
            }
            return [
                'success' => false,
                'error' => 'API клиент не настроен'
            ];
        }
        
        try {
            // Получение конфигурации из Shoper
            $response = $this->apiClient->get('shop_settings/configuration');
            
            if (!isset($response['settings'])) {
                if ($this->logger) {
                    $this->logger->error('Некорректный ответ от Shoper API', [
                        'response' => $response
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Некорректный ответ от Shoper API'
                ];
            }
            
            // Обработка полученных настроек
            $shoperSettings = $response['settings'];
            
            // Здесь можно выполнить синхронизацию специфичных настроек
            // Например, обновить языки, валюты и т.д.
            
            // Обновление информации о магазине в настройках приложения
            if (isset($shoperSettings['company_data'])) {
                $this->settings['shop_info'] = [
                    'name' => $shoperSettings['company_data']['name'] ?? '',
                    'url' => $shopUrl,
                    'email' => $shoperSettings['company_data']['email'] ?? '',
                    'synchronized_at' => date('Y-m-d H:i:s')
                ];
            }
            
            // Сохранение обновленных настроек
            $this->save();
            
            if ($this->logger) {
                $this->logger->info('Настройки успешно синхронизированы с Shoper', [
                    'shop_url' => $shopUrl
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Настройки успешно синхронизированы с Shoper'
            ];
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Ошибка при синхронизации настроек с Shoper', [
                    'message' => $e->getMessage(),
                    'shop_url' => $shopUrl
                ]);
            }
            return [
                'success' => false,
                'error' => 'Ошибка при синхронизации: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Обновляет настройки приложения в Shoper
     * 
     * @return array Результат обновления
     */
    public function updateShoperAppSettings(): array
    {
        if (!$this->apiClient) {
            if ($this->logger) {
                $this->logger->warning('Невозможно обновить настройки приложения в Shoper: API клиент не настроен');
            }
            return [
                'success' => false,
                'error' => 'API клиент не настроен'
            ];
        }
        
        try {
            // Подготовка данных для отправки в API
            $appSettings = [
                'ai_search_enabled' => $this->settings['search_enabled'] ?? false,
                'ai_search_limit' => $this->settings['result_limit'] ?? 10,
                'ai_search_replace_default' => $this->settings['replace_default_search'] ?? true,
                'ai_search_model' => $this->settings['model_name'] ?? 'gpt-4',
                'app_version' => $_ENV['APP_VERSION'] ?? '1.0.0'
            ];
            
            // Отправка настроек в Shoper API
            $response = $this->apiClient->put('applications/settings', [
                'settings' => json_encode($appSettings)
            ]);
            
            if (!isset($response['status']) || $response['status'] !== 'success') {
                if ($this->logger) {
                    $this->logger->error('Ошибка при обновлении настроек приложения в Shoper', [
                        'response' => $response
                    ]);
                }
                return [
                    'success' => false,
                    'error' => 'Ошибка при обновлении настроек в Shoper'
                ];
            }
            
            if ($this->logger) {
                $this->logger->info('Настройки приложения успешно обновлены в Shoper');
            }
            
            return [
                'success' => true,
                'message' => 'Настройки приложения успешно обновлены в Shoper'
            ];
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Ошибка при обновлении настроек приложения в Shoper', [
                    'message' => $e->getMessage()
                ]);
            }
            return [
                'success' => false,
                'error' => 'Ошибка при обновлении: ' . $e->getMessage()
            ];
        }
    }
}

