<?php

namespace ShoperAI\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Клиент для взаимодействия с API Shoper
 */
class ShoperApiClient
{
    /**
     * @var Client HTTP клиент для Shoper API
     */
    private Client $httpClient;
    
    /**
     * @var Client HTTP клиент для Trieve API
     */
    private Client $trieveHttpClient;

    /**
     * @var AuthenticationService Сервис аутентификации
     */
    private AuthenticationService $authService;

    /**
     * @var LoggerInterface Логгер
     */
    private LoggerInterface $logger;

    /**
     * @var string Базовый URL API
     */
    private string $apiUrl;

    /**
     * @var string Версия API
     */
    private string $apiVersion;

    /**
     * @var FilesystemAdapter Кэш для ответов API
     */
    private FilesystemAdapter $cache;

    /**
     * @var int Время жизни кэша по умолчанию (в секундах)
     */
    private int $defaultCacheLifetime = 3600;

    /**
     * @var array Ресурсы, которые не нужно кэшировать
     */
    private array $noCacheResources = ['cart', 'order', 'payment', 'webhook'];

    /**
     * Конструктор
     *
     * @param AuthenticationService $authService Сервис аутентификации
     * @param LoggerInterface $logger Логгер
     * @param string|null $apiUrl Опциональный URL API
     * @param string|null $apiVersion Опциональная версия API
     */
    public function __construct(
        AuthenticationService $authService,
        LoggerInterface $logger,
        ?string $apiUrl = null,
        ?string $apiVersion = null
    ) {
        $this->authService = $authService;
        $this->logger = $logger;
        $this->apiUrl = $apiUrl ?? $_ENV['SHOPER_API_URL'];
        $this->apiVersion = $apiVersion ?? $_ENV['SHOPER_API_VERSION'];

        $this->httpClient = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
        
        // Инициализируем HTTP клиент для Trieve
        $this->trieveHttpClient = new Client([
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        $this->cache = new FilesystemAdapter(
            'shoper_api',
            $this->defaultCacheLifetime,
            __DIR__ . '/../../var/cache'
        );
    }

    /**
     * GET запрос к API
     *
     * @param string $endpoint Конечная точка API
     * @param array $params Параметры запроса
     * @return array Данные ответа
     * @throws \Exception
     */
    public function get(string $endpoint, array $params = []): array
    {
        if ($this->shouldCacheResource($endpoint)) {
            $cacheKey = $this->generateCacheKey('GET', $endpoint, $params);
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($endpoint, $params) {
                $item->expiresAfter($this->defaultCacheLifetime);
                return $this->request('GET', $endpoint, ['query' => $params]);
            });
        }

        return $this->request('GET', $endpoint, ['query' => $params]);
    }

    /**
     * POST запрос к API
     *
     * @param string $endpoint Конечная точка API
     * @param array $data Данные запроса
     * @return array Данные ответа
     * @throws \Exception
     */
    public function post(string $endpoint, array $data = []): array
    {
        $result = $this->request('POST', $endpoint, ['json' => $data]);
        $this->invalidateResourceCache($endpoint);
        return $result;
    }

    /**
     * PUT запрос к API
     *
     * @param string $endpoint Конечная точка API
     * @param array $data Данные запроса
     * @return array Данные ответа
     * @throws \Exception
     */
    public function put(string $endpoint, array $data = []): array
    {
        $result = $this->request('PUT', $endpoint, ['json' => $data]);
        $this->invalidateResourceCache($endpoint);
        return $result;
    }

    /**
     * DELETE запрос к API
     *
     * @param string $endpoint Конечная точка API
     * @return array Данные ответа
     * @throws \Exception
     */
    public function delete(string $endpoint): array
    {
        $result = $this->request('DELETE', $endpoint);
        $this->invalidateResourceCache($endpoint);
        return $result;
    }

    /**
     * Выполнение запроса к API
     *
     * @param string $method HTTP метод
     * @param string $endpoint Конечная точка API
     * @param array $options Опции запроса
     * @return array Данные ответа
     * @throws \Exception
     */
    private function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            // Получаем токен
            $token = $this->authService->getAccessToken();
            
            // Добавляем заголовок авторизации
            $options['headers'] = $options['headers'] ?? [];
            $options['headers']['Authorization'] = "Bearer {$token}";
            
            // Формируем полный URL
            $fullEndpoint = $this->apiVersion . '/' . ltrim($endpoint, '/');
            
            $this->logger->info("Выполняется {$method} запрос к {$fullEndpoint}");
            
            // Выполняем запрос
            $response = $this->httpClient->request($method, $fullEndpoint, $options);
            
            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->logger->error("Ошибка API запроса: " . $e->getMessage(), [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("Ошибка API запроса: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Разбор ответа API
     *
     * @param ResponseInterface $response HTTP ответ
     * @return array Разобранные данные
     * @throws \Exception
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Ошибка разбора ответа API: " . json_last_error_msg());
            throw new \Exception("Ошибка разбора ответа API: " . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Проверка необходимости кэширования ресурса
     *
     * @param string $resource Ресурс API
     * @return bool
     */
    private function shouldCacheResource(string $resource): bool
    {
        foreach ($this->noCacheResources as $noCacheResource) {
            if (strpos($resource, $noCacheResource) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Генерация ключа кэша
     *
     * @param string $method HTTP метод
     * @param string $resource Ресурс API
     * @param array $params Параметры запроса
     * @return string
     */
    private function generateCacheKey(string $method, string $resource, array $params = []): string
    {
        $shopUrl = $this->authService->getShopUrl();
        $shopHash = md5($shopUrl ?? 'default');
        
        $key = "shoper_api_{$shopHash}_{$method}_{$resource}";
        
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        
        return $key;
    }

    /**
     * Инвалидация кэша для ресурса
     *
     * @param string $resource Ресурс API
     * @return bool
     */
    private function invalidateResourceCache(string $resource): bool
    {
        try {
            $pattern = "#^shoper_api_.+_{$resource}#";
            $keys = $this->cache->getItems([]);
            
            foreach ($keys as $key) {
                if (preg_match($pattern, $key->getKey())) {
                    $this->cache->deleteItem($key->getKey());
                }
            }
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при инвалидации кэша', [
                'message' => $e->getMessage(),
                'resource' => $resource
            ]);
            
            return false;
        }
    }
    
    /**
     * Индексирует продукт в Trieve
     *
     * @param array $product Данные о продукте
     * @return bool Результат индексации
     * @throws \Exception
     */
    public function indexProductInTrieve(array $product): bool
    {
        try {
            $apiKey = $_ENV['TRIEVE_API_KEY'] ?? null;
            if (empty($apiKey)) {
                throw new \Exception('API ключ Trieve не настроен');
            }
            
            $trieveEndpoint = $_ENV['TRIEVE_API_ENDPOINT'] ?? 'https://api.trieve.ai/api/v1';
            $indexEndpoint = rtrim($trieveEndpoint, '/') . '/chunk';
            
            // Подготавливаем данные о продукте для индексации
            $productData = [
                'tracking_id' => (string) $product['product_id'],
                'link' => $product['url'] ?? '',
                'content' => $this->prepareProductContentForTrieve($product),
                'metadata' => [
                    'product_id' => $product['product_id'],
                    'name' => $product['name'] ?? '',
                    'code' => $product['code'] ?? '',
                    'price' => $product['price'] ?? 0,
                    'category_id' => $product['category_id'] ?? 0,
                    'stock' => $product['stock'] ?? 0
                ]
            ];
            
            // Делаем запрос к Trieve API
            $response = $this->trieveHttpClient->request('POST', $indexEndpoint, [
                'headers' => [
                    'TR-Organization' => '48d78893-c8fb-4ab8-a78a-9ce51e381d80',
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $productData
            ]);
            
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при индексации продукта в Trieve', [
                'product_id' => $product['product_id'] ?? 'unknown',
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Подготавливает контент продукта для Trieve
     *
     * @param array $product Данные о продукте
     * @return string Подготовленный контент
     */
    private function prepareProductContentForTrieve(array $product): string
    {
        $content = [];
        
        // Добавляем название
        if (!empty($product['name'])) {
            $content[] = "Название: " . $product['name'];
        }
        
        // Добавляем описание
        if (!empty($product['description'])) {
            $content[] = "Описание: " . strip_tags($product['description']);
        }
        
        // Добавляем характеристики
        if (!empty($product['attributes'])) {
            $content[] = "Характеристики:";
            foreach ($product['attributes'] as $attr) {
                if (isset($attr['name']) && isset($attr['value'])) {
                    $content[] = "- {$attr['name']}: {$attr['value']}";
                }
            }
        }
        
        // Объединяем все в один текст
        return implode("\n", $content);
    }
    
    /**
     * Получает информацию о магазине
     *
     * @return array Информация о магазине
     * @throws \Exception
     */
    public function getShopInfo(): array
    {
        try {
            $response = $this->get('shop/info');
            
            return [
                'name' => $response['name'] ?? 'Unknown',
                'url' => $response['url'] ?? $this->authService->getShopUrl(),
                'api_version' => $this->apiVersion,
                'features' => $response['features'] ?? []
            ];
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении информации о магазине', [
                'message' => $e->getMessage()
            ]);
            
            // Возвращаем базовую информацию в случае ошибки
            return [
                'name' => 'Unknown',
                'url' => $this->authService->getShopUrl(),
                'api_version' => $this->apiVersion,
                'features' => []
            ];
        }
    }
}
