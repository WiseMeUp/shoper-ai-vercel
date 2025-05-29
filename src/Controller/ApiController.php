<?php

namespace ShoperAI\Controller;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpClient\HttpClient;
use ShoperAI\Service\ShoperApiClient;
use ShoperAI\Model\AdminSettings;

/**
 * Контроллер API для обработки поисковых запросов с использованием Trieve
 * 
 * Обеспечивает обработку поисковых запросов, интеграцию с Trieve,
 * фильтрацию результатов и кэширование.
 */
class ApiController
{
    /**
     * Главная страница приложения
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function index(Request $request): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение к магазину Shoper</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        form {
            margin-top: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .info {
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Подключение к магазину Shoper</h1>
        
        <div class="info">
            <p>Введите URL вашего магазина Shoper для подключения приложения AI Search.</p>
        </div>
        
        <form action="/oauth/start" method="GET">
            <label for="shop">URL магазина Shoper:</label>
            <input type="text" id="shop" name="shop" placeholder="например: your-shop.myshoper.cloud" required>
            
            <button type="submit">Подключить</button>
        </form>
    </div>
</body>
</html>
HTML;

        return new Response($html);
    }
    /**
     * @var Logger Экземпляр логгера
     */
    private Logger $logger;
    
    /**
     * @var ShoperApiClient Клиент API Shoper
     */
    private ShoperApiClient $apiClient;
    
    /**
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface Клиент HTTP для Trieve API
     */
    private \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient;
    
    /**
     * @var AdminSettings Модель настроек
     */
    private AdminSettings $settings;
    
    /**
     * @var FilesystemAdapter Кэш для результатов поиска
     */
    private FilesystemAdapter $cache;
    
    /**
     * @var int Время жизни кэша по умолчанию (в секундах)
     */
    private int $defaultCacheLifetime = 3600;
    
    /**
     * Конструктор
     *
     * @param Logger $logger Экземпляр логгера
     * @param ShoperApiClient $apiClient Клиент API Shoper
     * @param AdminSettings $settings Модель настроек
     */
    public function __construct(
        Logger $logger, 
        ShoperApiClient $apiClient, 
        AdminSettings $settings
    ) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->settings = $settings;
        
        // Инициализация кэша
        $this->cache = new FilesystemAdapter(
            'trieve_search',
            $this->defaultCacheLifetime,
            __DIR__ . '/../../tmp/cache'
        );
        
        // Инициализация HTTP клиента для Trieve
        $this->httpClient = HttpClient::create([
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);
        
        $this->logger->info('ApiController инициализирован с Trieve API');
    }
    
    /**
     * Обрабатывает поисковый запрос с использованием AI
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function search(Request $request): Response
    {
        try {
            // Проверяем, включен ли AI-поиск в настройках
            if (!$this->settings->get('search_enabled', false)) {
                $this->logger->info('Запрос поиска отклонен: AI-поиск отключен в настройках');
                
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'AI-поиск отключен в настройках'
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
            
            // Получаем параметры запроса
            $query = $request->query->get('q');
            $limit = (int) $request->query->get('limit', $this->settings->get('result_limit', 10));
            $page = (int) $request->query->get('page', 1);
            $categoryId = (int) $request->query->get('category', 0);
            
            // Валидация запроса
            if (empty($query)) {
                $this->logger->info('Пустой поисковый запрос');
                
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Поисковый запрос не может быть пустым'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Очищаем и нормализуем запрос
            $query = $this->sanitizeQuery($query);
            
            // Пытаемся получить результаты из кэша
            $cacheKey = $this->generateSearchCacheKey($query, $limit, $page, $categoryId);
            
            try {
                $results = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $limit, $page, $categoryId) {
                    // Устанавливаем время жизни кэша
                    $item->expiresAfter($this->defaultCacheLifetime);
                    
                    // Выполняем поиск с использованием AI
                    return $this->performAISearch($query, $limit, $page, $categoryId);
                });
                
                $this->logger->info('Успешный поисковый запрос', [
                    'query' => $query,
                    'results_count' => count($results['items'] ?? []),
                    'cache_hit' => true
                ]);
            } catch (\Exception $e) {
                // Если произошла ошибка с кэшем, выполняем поиск напрямую
                $this->logger->warning('Ошибка при работе с кэшем поиска', [
                    'message' => $e->getMessage()
                ]);
                
                $results = $this->performAISearch($query, $limit, $page, $categoryId);
                
                $this->logger->info('Успешный поисковый запрос', [
                    'query' => $query,
                    'results_count' => count($results['items'] ?? []),
                    'cache_hit' => false
                ]);
            }
            
            return new JsonResponse($results);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при обработке поискового запроса', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->query->get('q')
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Произошла ошибка при обработке поискового запроса: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Проверяет статус API
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function status(Request $request): Response
    {
        try {
            // Проверяем статус API Shoper
            $shopInfo = $this->apiClient->getShopInfo();
            
            // Проверяем статус Trieve API
            $trieveStatus = $this->checkTrieveStatus();
            
            $status = [
                'status' => 'ok',
                'shop_api' => [
                    'status' => 'ok',
                    'shop_name' => $shopInfo['name'] ?? 'Unknown',
                    'shop_url' => $shopInfo['url'] ?? 'Unknown'
                ],
                'search_api' => [
                    'status' => $trieveStatus ? 'ok' : 'error',
                    'provider' => 'Trieve',
                    'enabled' => $this->settings->get('search_enabled', false)
                ],
                'config' => [
                    'search_enabled' => $this->settings->get('search_enabled', false),
                    'result_limit' => $this->settings->get('result_limit', 10),
                    'include_descriptions' => $this->settings->get('include_descriptions', true)
                ],
                'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
                'timestamp' => time()
            ];
            
            $this->logger->info('Запрос статуса API');
            
            return new JsonResponse($status);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при проверке статуса API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Произошла ошибка при проверке статуса API: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Публичная информация о приложении
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function publicInfo(Request $request): Response
    {
        $info = [
            'app_name' => 'Shoper AI Search',
            'description' => 'Интеллектуальный поиск с использованием искусственного интеллекта',
            'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
            'enabled' => $this->settings->get('search_enabled', false),
            'timestamp' => time()
        ];
        
        $this->logger->info('Запрос публичной информации о приложении');
        
        return new JsonResponse($info);
    }
    
    /**
     * Очищает кэш поисковых запросов
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function clearSearchCache(Request $request): Response
    {
        try {
            $this->cache->clear();
            
            $this->logger->info('Кэш поисковых запросов очищен');
            
            return new JsonResponse([
                'status' => 'success',
                'message' => 'Кэш поисковых запросов успешно очищен'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при очистке кэша поисковых запросов', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Произошла ошибка при очистке кэша поисковых запросов: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Выполняет поиск с использованием Trieve
     *
     * @param string $query Поисковый запрос
     * @param int $limit Лимит результатов
     * @param int $page Номер страницы
     * @param int $categoryId ID категории (0 - все категории)
     * @return array Результаты поиска
     * @throws \Exception Если произошла ошибка
     */
    private function performAISearch(string $query, int $limit, int $page, int $categoryId = 0): array
    {
        // Шаг 1: Формируем параметры запроса для Trieve
        $searchParams = [
            'query' => $query,
            'limit' => $limit,
            'page' => $page,
            'filters' => []
        ];
        
        if ($categoryId > 0) {
            $searchParams['filters']['category_id'] = $categoryId;
        }
        
        // Шаг 2: Выполняем поиск через Trieve API
        $searchResults = $this->searchWithTrieve($searchParams);
        
        // Если результаты пустые, возвращаем пустой ответ
        if (empty($searchResults['hits'] ?? [])) {
            return [
                'status' => 'success',
                'message' => 'Ничего не найдено',
                'items' => [],
                'count' => 0,
                'query' => $query
            ];
        }
        
        // Шаг 3: Извлекаем идентификаторы продуктов из результатов Trieve
        $productIds = array_map(function($hit) {
            return $hit['product_id'] ?? $hit['id'] ?? null;
        }, $searchResults['hits']);
        
        $productIds = array_filter($productIds); // Удаляем пустые значения
        
        // Шаг 4: Получаем полные данные о продуктах из Shoper API
        $products = $this->getProductsByIds($productIds);
        
        // Шаг 5: Сортируем продукты в том же порядке, что и результаты поиска
        $rankedProducts = $this->mergeSearchResultsWithProducts($searchResults['hits'], $products);
        
        // Шаг 6: Формируем результаты для вывода
        $results = [
            'status' => 'success',
            'message' => 'Результаты поиска',
            'items' => $rankedProducts,
            'count' => $searchResults['total_hits'] ?? count($rankedProducts),
            'page' => $page,
            'limit' => $limit,
            'query' => $query,
            'category_id' => $categoryId,
            'total_pages' => $searchResults['total_pages'] ?? ceil(($searchResults['total_hits'] ?? count($rankedProducts)) / $limit)
        ];
        
        return $results;
    }
    
    /**
     * Расширяет данные о товарах дополнительной информацией
     *
     * @param array $products Список товаров
     * @return array Расширенный список товаров
     */
    private function enrichProductData(array $products): array
    {
        $enrichedProducts = [];
        
        foreach ($products as $product) {
            $productId = $product['product_id'];
            
            try {
                // Получаем полную информацию о товаре
                $productDetails = $this->apiClient->getProduct($productId);
                
                // Добавляем описание и другие детали
                $product['description'] = $productDetails['description'] ?? '';
                $product['attributes'] = $productDetails['attributes'] ?? [];
                
                $enrichedProducts[] = $product;
            } catch (\Exception $e) {
                $this->logger->warning('Не удалось получить детали товара', [
                    'product_id' => $productId,
                    'message' => $e->getMessage()
                ]);
                
                // Добавляем товар без обогащения
                $enrichedProducts[] = $product;
            }
        }
        
        return $enrichedProducts;
    }
    
    /**
     * Подготавливает товары для анализа с помощью AI
     *
     * @param array $products Список товаров
     * @return array Подготовленный список товаров
     */
    private function prepareProductsForAnalysis(array $products): array
    {
        $preparedProducts = [];
        
        foreach ($products as $product) {
            // Собираем только необходимые данные для анализа
            $preparedProduct = [
                'product_id' => $product['product_id'],
                'code' => $product['code'] ?? '',
                'name' => $product['name'] ?? '',
                'price' => $product['price'] ?? 0,
                'stock' => $product['stock'] ?? 0,
                'url' => $product['url'] ?? '',
                'category_id' => $product['category_id'] ?? 0,
                'images' => $product['images'] ?? []
            ];
            
            // Добавляем описание, если оно есть
            if (isset($product['description']) && !empty($product['description'])) {
                $preparedProduct['description'] = strip_tags($product['description']);
            }
            
            // Добавляем атрибуты, если они есть
            if (isset($product['attributes']) && !empty($product['attributes'])) {
                $preparedProduct['attributes'] = $this->formatAttributes($product['attributes']);
            }
            
            $preparedProducts[] = $preparedProduct;
        }
        
        return $preparedProducts;
    }
    
    /**
     * Форматирует атрибуты товара для анализа
     *
     * @param array $attributes Атрибуты товара
     * @return array Форматированные атрибуты
     */
    private function formatAttributes(array $attributes): array
    {
        $formatted = [];
        
        foreach ($attributes as $attribute) {
            if (isset($attribute['name']) && isset($attribute['value'])) {
                $formatted[] = $attribute['name'] . ': ' . $attribute['value'];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Выполняет поиск через Trieve API
     *
     * @param array $params Параметры поиска
     * @return array Результаты поиска
     * @throws \Exception Если произошла ошибка
     */
    private function searchWithTrieve(array $params): array
    {
        try {
            $apiKey = $_ENV['TRIEVE_API_KEY'] ?? null;
            if (empty($apiKey)) {
                throw new \Exception('API ключ Trieve не настроен');
            }
            
            $trieveEndpoint = $_ENV['TRIEVE_API_ENDPOINT'] ?? 'https://api.trieve.ai/api/v1';
            $searchEndpoint = rtrim($trieveEndpoint, '/') . '/search';
            
            // Подготавливаем параметры запроса для Trieve
            $requestData = [
                'query' => $params['query'],
                'search_type' => 'semantic', // Используем семантический поиск
                'page' => $params['page'],
                'page_size' => $params['limit']
            ];
            
            // Добавляем фильтры, если они есть
            if (!empty($params['filters'])) {
                $requestData['filter'] = $params['filters'];
            }
            
            // Выполняем запрос к Trieve API
            $response = $this->httpClient->request('POST', $searchEndpoint, [
                'headers' => [
                    'TR-Organization' => '48d78893-c8fb-4ab8-a78a-9ce51e381d80',
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestData
            ]);
            
            $content = $response->getContent();
            $results = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Некорректный формат ответа от Trieve API');
            }
            
            // Преобразуем ответ Trieve в нужный формат
            return [
                'hits' => $results['chunks'] ?? [],
                'total_hits' => $results['total'] ?? 0,
                'total_pages' => ceil(($results['total'] ?? 0) / $params['limit'])
            ];
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при выполнении поиска через Trieve API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Получает продукты по их идентификаторам
     *
     * @param array $productIds Массив идентификаторов продуктов
     * @return array Массив продуктов
     */
    private function getProductsByIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        
        $products = [];
        
        foreach ($productIds as $productId) {
            try {
                // Получаем полную информацию о товаре из Shoper API
                $productData = $this->apiClient->getProduct($productId);
                
                if ($productData) {
                    $products[$productId] = $productData;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Не удалось получить товар по ID', [
                    'product_id' => $productId,
                    'message' => $e->getMessage()
                ]);
            }
        }
        
        return $products;
    }
    
    /**
     * Объединяет результаты поиска с полными данными о продуктах
     *
     * @param array $searchHits Результаты поиска от Trieve
     * @param array $products Продукты из Shoper API
     * @return array Объединенные данные
     */
    private function mergeSearchResultsWithProducts(array $searchHits, array $products): array
    {
        $result = [];
        
        foreach ($searchHits as $hit) {
            $productId = $hit['product_id'] ?? $hit['id'] ?? null;
            
            if ($productId && isset($products[$productId])) {
                $product = $products[$productId];
                
                // Добавляем информацию о релевантности из результатов поиска
                $product['relevance'] = $hit['score'] ?? $hit['relevance'] ?? 0;
                $product['relevance_explanation'] = $hit['explanation'] ?? $hit['highlights'] ?? 'Найдено через Trieve';
                
                $result[] = $product;
            }
        }
        
        return $result;
    }
    
    /**
     * Резервное ранжирование товаров при ошибке AI
     *
     * @param array $products Список товаров
     * @param string $query Поисковый запрос
     * @return array Ранжированный список товаров
     */
    private function fallbackRanking(array $products, string $query): array
    {
        // Разбиваем запрос на слова
        $queryWords = explode(' ', strtolower($query));
        
        // Оцениваем релевантность каждого товара
        foreach ($products as &$product) {
            $relevance = 0;
            $name = strtolower($product['name'] ?? '');
            $description = strtolower($product['description'] ?? '');
            
            // Проверяем вхождение слов из запроса в название и описание
            foreach ($queryWords as $word) {
                if (strpos($name, $word) !== false) {
                    // Слово найдено в названии (более важно)
                    $relevance += 2;
                }
                
                if (strpos($description, $word) !== false) {
                    // Слово найдено в описании
                    $relevance += 1;
                }
            }
            
            // Добавляем оценку релевантности
            $product['relevance'] = $relevance;
            $product['relevance_explanation'] = 'Найдено через стандартный поиск';
        }
        
        // Сортируем товары по релевантности (по убыванию)
        usort($products, function ($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });
        
        return $products;
    }
    
    /**
     * Проверяет статус Trieve API
     *
     * @return bool Результат проверки
     */
    private function checkTrieveStatus(): bool
    {
        try {
            $apiKey = $_ENV['TRIEVE_API_KEY'] ?? null;
            
            if (empty($apiKey)) {
                $this->logger->warning('API ключ Trieve не настроен');
                return false;
            }
            
            $trieveEndpoint = $_ENV['TRIEVE_API_ENDPOINT'] ?? 'https://api.trieve.ai/api/v1';
            $healthEndpoint = rtrim($trieveEndpoint, '/') . '/health';
            
            // Выполняем запрос для проверки статуса
            $response = $this->httpClient->request('GET', $healthEndpoint, [
                'headers' => [
                    'TR-Organization' => '48d78893-c8fb-4ab8-a78a-9ce51e381d80',
                    'Authorization' => 'Bearer ' . $apiKey
                ]
            ]);
            
            $statusCode = $response->getStatusCode();
            
            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при проверке статуса Trieve API', [
                'message' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Индексирует продукт в Trieve
     *
     * @param array $product Данные о продукте
     * @return bool Результат индексации
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
            
            // Выполняем запрос к Trieve API для индексации продукта
            $response = $this->httpClient->request('POST', $indexEndpoint, [
                'headers' => [
                    'TR-Organization' => '48d78893-c8fb-4ab8-a78a-9ce51e381d80',
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $productData
            ]);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Продукт успешно индексирован в Trieve', [
                    'product_id' => $product['product_id']
                ]);
                
                return true;
            } else {
                $this->logger->warning('Ошибка при индексации продукта в Trieve', [
                    'product_id' => $product['product_id'],
                    'status_code' => $statusCode
                ]);
                
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при индексации продукта в Trieve', [
                'product_id' => $product['product_id'] ?? 'unknown',
                'message' => $e->getMessage()
            ]);
            
            return false;
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
     * Очищает и нормализует поисковый запрос
     *
     * @param string $query Поисковый запрос
     * @return string Очищенный запрос
     */
    private function sanitizeQuery(string $query): string
    {
        // Удаляем HTML-теги
        $query = strip_tags($query);
        
        // Удаляем лишние пробелы
        $query = trim(preg_replace('/\s+/', ' ', $query));
        
        // Ограничиваем длину запроса
        $query = substr($query, 0, 255);
        
        return $query;
    }
    
    /**
     * Генерирует ключ кэша для поискового запроса
     *
     * @param string $query Поисковый запрос
     * @param int $limit Лимит результатов
     * @param int $page Номер страницы
     * @param int $categoryId ID категории
     * @return string Ключ кэша
     */
    private function generateSearchCacheKey(string $query, int $limit, int $page, int $categoryId): string
    {
        $shopUrl = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'default';
        $shopHash = md5($shopUrl);
        
        return "ai_search_{$shopHash}_" . md5("{$query}_{$limit}_{$page}_{$categoryId}");
    }
}

