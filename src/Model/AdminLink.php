<?php

namespace ShoperAI\Model;

use Monolog\Logger;

/**
 * Класс AdminLink для управления административными ссылками
 * 
 * Обеспечивает методы для создания, управления и валидации
 * ссылок в административной панели.
 */
class AdminLink
{
    /**
     * @var string Путь к файлу со ссылками
     */
    private string $linksPath;
    
    /**
     * @var array Массив всех ссылок
     */
    private array $links = [];
    
    /**
     * @var Logger|null Логгер
     */
    private ?Logger $logger;
    
    /**
     * @var string Уникальный идентификатор ссылки
     */
    private string $id;
    
    /**
     * @var string Название ссылки
     */
    private string $name;
    
    /**
     * @var string URL адрес назначения
     */
    private string $url;
    
    /**
     * @var string Связанный объект
     */
    private string $object;
    
    /**
     * @var string Действие
     */
    private string $action;
    
    /**
     * @var string Место размещения ссылки
     */
    private string $placement;
    
    /**
     * @var string Способ открытия: 'panel' или 'iframe'
     */
    private string $openType;
    
    /**
     * @var array Права доступа
     */
    private array $permissions = [];
    
    /**
     * @var string Область действия прав
     */
    private string $scope;
    
    /**
     * @var string Дата и время создания
     */
    private string $createdAt;
    
    /**
     * @var string Дата и время обновления
     */
    private string $updatedAt;

    /**
     * Конструктор
     * 
     * @param Logger|null $logger Логгер (опционально)
     * @param array|null $data Данные для инициализации (опционально)
     */
    public function __construct(?Logger $logger = null, ?array $data = null)
    {
        $basePath = realpath(__DIR__ . '/../../');
        $this->linksPath = $basePath . '/database/admin_links.json';
        
        // Создание директории, если она не существует
        if (!is_dir(dirname($this->linksPath))) {
            mkdir(dirname($this->linksPath), 0755, true);
        }
        
        $this->logger = $logger;
        
        // Установка дат по умолчанию
        $currentDateTime = date('Y-m-d H:i:s');
        $this->createdAt = $currentDateTime;
        $this->updatedAt = $currentDateTime;
        
        // Генерация уникального ID
        $this->id = $this->generateUniqueId();
        
        // Инициализация из переданных данных
        if ($data !== null) {
            $this->setFromArray($data);
        }
        
        // Загрузка всех ссылок
        $this->loadLinks();
    }
    
    /**
     * Генерирует уникальный идентификатор
     * 
     * @return string Уникальный ID
     */
    private function generateUniqueId(): string
    {
        return uniqid('link_', true);
    }
    
    /**
     * Устанавливает свойства из массива
     * 
     * @param array $data Массив данных
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setFromArray(array $data): self
    {
        // Установка основных свойств
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
        
        if (isset($data['name'])) {
            $this->name = $data['name'];
        }
        
        if (isset($data['url'])) {
            $this->url = $data['url'];
        }
        
        if (isset($data['object'])) {
            $this->object = $data['object'];
        }
        
        if (isset($data['action'])) {
            $this->action = $data['action'];
        }
        
        if (isset($data['placement'])) {
            $this->placement = $data['placement'];
        }
        
        if (isset($data['openType'])) {
            $this->openType = $data['openType'];
        }
        
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $this->permissions = $data['permissions'];
        }
        
        if (isset($data['scope'])) {
            $this->scope = $data['scope'];
        }
        
        if (isset($data['createdAt'])) {
            $this->createdAt = $data['createdAt'];
        }
        
        if (isset($data['updatedAt'])) {
            $this->updatedAt = $data['updatedAt'];
        }
        
        return $this;
    }
    
    /**
     * Преобразует объект в массив
     * 
     * @return array Массив свойств
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'url' => $this->url ?? '',
            'object' => $this->object ?? '',
            'action' => $this->action ?? '',
            'placement' => $this->placement ?? '',
            'openType' => $this->openType ?? 'panel',
            'permissions' => $this->permissions,
            'scope' => $this->scope ?? '',
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt
        ];
    }
    
    /**
     * Загружает все ссылки из файла
     * 
     * @return array Массив всех ссылок
     */
    public function loadLinks(): array
    {
        if (file_exists($this->linksPath)) {
            $content = file_get_contents($this->linksPath);
            if ($content) {
                try {
                    $links = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    
                    // Проверка на корректность структуры
                    if (is_array($links)) {
                        $this->links = $links;
                        return $this->links;
                    }
                } catch (\JsonException $e) {
                    if ($this->logger) {
                        $this->logger->error('Ошибка при чтении файла ссылок', [
                            'message' => $e->getMessage(),
                            'file' => $this->linksPath
                        ]);
                    }
                }
            }
        }
        
        // Если файл не существует или некорректен, инициализируем пустым массивом
        $this->links = [];
        return $this->links;
    }
    
    /**
     * Сохраняет все ссылки в файл
     * 
     * @return bool Результат операции
     */
    public function saveLinks(): bool
    {
        try {
            $jsonData = json_encode($this->links, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $result = file_put_contents($this->linksPath, $jsonData, LOCK_EX);
            
            if ($result === false) {
                if ($this->logger) {
                    $this->logger->error('Не удалось сохранить ссылки', [
                        'file' => $this->linksPath
                    ]);
                }
                return false;
            }
            
            // Дополнительная проверка записи
            clearstatcache();
            if (!file_exists($this->linksPath) || filesize($this->linksPath) === 0) {
                if ($this->logger) {
                    $this->logger->error('Файл ссылок пуст после сохранения', [
                        'file' => $this->linksPath
                    ]);
                }
                return false;
            }
            
            if ($this->logger) {
                $this->logger->info('Ссылки успешно сохранены', [
                    'file' => $this->linksPath,
                    'count' => count($this->links)
                ]);
            }
            
            return true;
        } catch (\JsonException $e) {
            if ($this->logger) {
                $this->logger->error('Ошибка при сохранении ссылок', [
                    'message' => $e->getMessage(),
                    'file' => $this->linksPath
                ]);
            }
            return false;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Непредвиденная ошибка при сохранении ссылок', [
                    'message' => $e->getMessage(),
                    'file' => $this->linksPath
                ]);
            }
            return false;
        }
    }
    
    /**
     * Сохраняет текущую ссылку
     * 
     * @return bool Результат операции
     */
    public function save(): bool
    {
        // Обновляем дату изменения
        $this->updatedAt = date('Y-m-d H:i:s');
        
        // Преобразуем объект в массив
        $linkData = $this->toArray();
        
        // Проверяем, существует ли ссылка с таким ID
        $exists = false;
        foreach ($this->links as $key => $link) {
            if ($link['id'] === $this->id) {
                $this->links[$key] = $linkData;
                $exists = true;
                break;
            }
        }
        
        // Если ссылка не существует, добавляем ее
        if (!$exists) {
            $this->links[] = $linkData;
        }
        
        // Сохраняем все ссылки
        return $this->saveLinks();
    }
    
    /**
     * Удаляет ссылку по идентификатору
     * 
     * @param string $id Идентификатор ссылки
     * @return bool Результат операции
     */
    public function delete(string $id): bool
    {
        foreach ($this->links as $key => $link) {
            if ($link['id'] === $id) {
                unset($this->links[$key]);
                // Переиндексируем массив
                $this->links = array_values($this->links);
                
                if ($this->logger) {
                    $this->logger->info('Ссылка удалена', [
                        'id' => $id
                    ]);
                }
                
                return $this->saveLinks();
            }
        }
        
        if ($this->logger) {
            $this->logger->warning('Попытка удаления несуществующей ссылки', [
                'id' => $id
            ]);
        }
        
        return false;
    }
    
    /**
     * Валидирует данные ссылки
     * 
     * @param array $data Данные для валидации
     * @return array Массив с результатами валидации ['success' => bool, 'errors' => array]
     */
    public function validate(array $data): array
    {
        $errors = [];
        
        // Проверка имени
        if (empty($data['name'])) {
            $errors[] = 'Название ссылки не может быть пустым';
        }
        
        // Проверка URL
        if (empty($data['url'])) {
            $errors[] = 'URL не может быть пустым';
        } elseif (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URL должен быть корректным';
        }
        
        // Проверка способа открытия
        if (isset($data['openType']) && !in_array($data['openType'], ['panel', 'iframe'])) {
            $errors[] = 'Способ открытия должен быть "panel" или "iframe"';
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Находит ссылку по идентификатору
     * 
     * @param string $id Идентификатор ссылки
     * @return self|null Найденная ссылка или null
     */
    public static function findById(string $id, ?Logger $logger = null): ?self
    {
        $instance = new self($logger);
        $links = $instance->loadLinks();
        
        foreach ($links as $linkData) {
            if ($linkData['id'] === $id) {
                return (new self($logger))->setFromArray($linkData);
            }
        }
        
        return null;
    }
    
    /**
     * Получает все ссылки
     * 
     * @param Logger|null $logger Логгер (опционально)
     * @return array Массив всех ссылок
     */
    public static function getAll(?Logger $logger = null): array
    {
        $instance = new self($logger);
        return $instance->loadLinks();
    }
    
    /**
     * Получает ссылки по определенному критерию
     * 
     * @param string $field Поле для фильтрации
     * @param mixed $value Значение для фильтрации
     * @param Logger|null $logger Логгер (опционально)
     * @return array Отфильтрованные ссылки
     */
    public static function getBy(string $field, $value, ?Logger $logger = null): array
    {
        $instance = new self($logger);
        $links = $instance->loadLinks();
        $result = [];
        
        foreach ($links as $linkData) {
            if (isset($linkData[$field]) && $linkData[$field] === $value) {
                $result[] = $linkData;
            }
        }
        
        return $result;
    }
    
    // Геттеры и сеттеры
    
    /**
     * Получает идентификатор ссылки
     * 
     * @return string Идентификатор
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Получает название ссылки
     * 
     * @return string Название
     */
    public function getName(): string
    {
        return $this->name ?? '';
    }
    
    /**
     * Устанавливает название ссылки
     * 
     * @param string $name Название
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * Получает URL ссылки
     * 
     * @return string URL
     */
    public function getUrl(): string
    {
        return $this->url ?? '';
    }
    
    /**
     * Устанавливает URL ссылки
     * 
     * @param string $url URL
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }
    
    /**
     * Получает объект ссылки
     * 
     * @return string Объект
     */
    public function getObject(): string
    {
        return $this->object ?? '';
    }
    
    /**
     * Устанавливает объект ссылки
     * 
     * @param string $object Объект
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setObject(string $object): self
    {
        $this->object = $object;
        return $this;
    }
    
    /**
     * Получает действие ссылки
     * 
     * @return string Действие
     */
    public function getAction(): string
    {
        return $this->action ?? '';
    }
    
    /**
     * Устанавливает действие ссылки
     * 
     * @param string $action Действие
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }
    
    /**
     * Получает место размещения ссылки
     * 
     * @return string Место размещения
     */
    public function getPlacement(): string
    {
        return $this->placement ?? '';
    }
    
    /**
     * Устанавливает место размещения ссылки
     * 
     * @param string $placement Место размещения
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setPlacement(string $placement): self
    {
        $this->placement = $placement;
        return $this;
    }
    
    /**
     * Получает способ открытия ссылки
     * 
     * @return string Способ открытия
     */
    public function getOpenType(): string
    {
        return $this->openType ?? 'panel';
    }
    
    /**
     * Устанавливает способ открытия ссылки
     * 
     * @param string $openType Способ открытия ('panel' или 'iframe')
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setOpenType(string $openType): self
    {
        if (in_array($openType, ['panel', 'iframe'])) {
            $this->openType = $openType;
        }
        return $this;
    }
    
    /**
     * Получает права доступа
     * 
     * @return array Права доступа
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
    
    /**
     * Устанавливает права доступа
     * 
     * @param array $permissions Права доступа
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }
    
    /**
     * Получает область действия прав
     * 
     * @return string Область действия
     */
    public function getScope(): string
    {
        return $this->scope ?? '';
    }
    
    /**
     * Устанавливает область действия прав
     * 
     * @param string $scope Область действия
     * @return self Текущий экземпляр для цепочки вызовов
     */
    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }
    
    /**
     * Получает дату создания
     * 
     * @return string Дата создания
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
    
    /**
     * Получает дату обновления
     * 
     * @return string Дата обновления
     */
    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }
}

