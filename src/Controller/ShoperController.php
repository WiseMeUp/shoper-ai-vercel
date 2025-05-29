<?php

namespace ShoperAI\Controller;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ShoperAI\Service\ShoperApiClient;
use ShoperAI\Model\AdminSettings;

/**
 * Контроллер для работы с API Shoper
 * 
 * Обеспечивает взаимодействие с API Shoper для получения и управления
 * продуктами, категориями, заказами и клиентами.
 */
class ShoperController
{
    /**
     * @var Logger Экземпляр логгера
     */
    private Logger $logger;
    
    /**
     * @var ShoperApiClient Клиент API Shoper
     */
    private ShoperApiClient $apiClient;
    
    /**
     * @var AdminSettings Модель настроек
     */
    private AdminSettings $settings;
    
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
        
        $this->logger->info('ShoperController инициализирован');
    }
    
    /**
     * Получает список продуктов
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function getProducts(Request $request): Response
    {
        try {
            $limit = (int)$request->query->get('limit', 25);
            $page = (int)$request->query->get('page', 1);
            $filters = $this->parseFilters($request);
            
            $this->logger->info('Запрос списка продуктов', [
                'limit' => $limit,
                'page' => $page,
                'filters' => $filters
            ]);
            
            $queryParams = [
                'limit' => $limit,
                'page' => $page
            ];
            
            // Добавляем фильтры в запрос
            foreach ($filters as $key => $value) {
                $queryParams[$key] = $value;
            }
            
            $products = $this->apiClient->get('products', $queryParams);
            
            $this->logger->info('Получен список продуктов', [
                'count' => count($products['list'] ?? []),
                'total' => $products['count'] ?? 0
            ]);
            
            return new JsonResponse($products);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении списка продуктов', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении списка продуктов: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает информацию о конкретном продукте
     *
     * @param Request $request HTTP запрос
     * @param int $id Идентификатор продукта
     * @return Response HTTP ответ
     */
    public function getProduct(Request $request, int $id): Response
    {
        try {
            $this->logger->info('Запрос информации о продукте', [
                'id' => $id
            ]);
            
            $product = $this->apiClient->get('products/' . $id);
            
            $this->logger->info('Получена информация о продукте', [
                'id' => $id,
                'name' => $product['name'] ?? 'Unknown'
            ]);
            
            return new JsonResponse($product);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении информации о продукте', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении информации о продукте: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает категории продукта
     *
     * @param Request $request HTTP запрос
     * @param int $id Идентификатор продукта
     * @return Response HTTP ответ
     */
    public function getProductCategories(Request $request, int $id): Response
    {
        try {
            $this->logger->info('Запрос категорий продукта', [
                'id' => $id
            ]);
            
            $categories = $this->apiClient->get('products/' . $id . '/categories');
            
            $this->logger->info('Получены категории продукта', [
                'id' => $id,
                'count' => count($categories['list'] ?? [])
            ]);
            
            return new JsonResponse($categories);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении категорий продукта', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении категорий продукта: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает список категорий
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function getCategories(Request $request): Response
    {
        try {
            $limit = (int)$request->query->get('limit', 25);
            $page = (int)$request->query->get('page', 1);
            $filters = $this->parseFilters($request);
            
            $this->logger->info('Запрос списка категорий', [
                'limit' => $limit,
                'page' => $page,
                'filters' => $filters
            ]);
            
            $queryParams = [
                'limit' => $limit,
                'page' => $page
            ];
            
            // Добавляем фильтры в запрос
            foreach ($filters as $key => $value) {
                $queryParams[$key] = $value;
            }
            
            $categories = $this->apiClient->get('categories', $queryParams);
            
            $this->logger->info('Получен список категорий', [
                'count' => count($categories['list'] ?? []),
                'total' => $categories['count'] ?? 0
            ]);
            
            return new JsonResponse($categories);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении списка категорий', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении списка категорий: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает информацию о категории
     *
     * @param Request $request HTTP запрос
     * @param int $id Идентификатор категории
     * @return Response HTTP ответ
     */
    public function getCategory(Request $request, int $id): Response
    {
        try {
            $this->logger->info('Запрос информации о категории', [
                'id' => $id
            ]);
            
            $category = $this->apiClient->get('categories/' . $id);
            
            $this->logger->info('Получена информация о категории', [
                'id' => $id,
                'name' => $category['name'] ?? 'Unknown'
            ]);
            
            return new JsonResponse($category);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении информации о категории', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении информации о категории: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает список заказов
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function getOrders(Request $request): Response
    {
        try {
            $limit = (int)$request->query->get('limit', 25);
            $page = (int)$request->query->get('page', 1);
            $filters = $this->parseFilters($request);
            
            $this->logger->info('Запрос списка заказов', [
                'limit' => $limit,
                'page' => $page,
                'filters' => $filters
            ]);
            
            $queryParams = [
                'limit' => $limit,
                'page' => $page
            ];
            
            // Добавляем фильтры в запрос
            foreach ($filters as $key => $value) {
                $queryParams[$key] = $value;
            }
            
            $orders = $this->apiClient->get('orders', $queryParams);
            
            $this->logger->info('Получен список заказов', [
                'count' => count($orders['list'] ?? []),
                'total' => $orders['count'] ?? 0
            ]);
            
            return new JsonResponse($orders);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении списка заказов', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении списка заказов: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает информацию о заказе
     *
     * @param Request $request HTTP запрос
     * @param int $id Идентификатор заказа
     * @return Response HTTP ответ
     */
    public function getOrder(Request $request, int $id): Response
    {
        try {
            $this->logger->info('Запрос информации о заказе', [
                'id' => $id
            ]);
            
            $order = $this->apiClient->get('orders/' . $id);
            
            $this->logger->info('Получена информация о заказе', [
                'id' => $id,
                'status' => $order['status_name'] ?? 'Unknown'
            ]);
            
            return new JsonResponse($order);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении информации о заказе', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении информации о заказе: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает список клиентов
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function getCustomers(Request $request): Response
    {
        try {
            $limit = (int)$request->query->get('limit', 25);
            $page = (int)$request->query->get('page', 1);
            $filters = $this->parseFilters($request);
            
            $this->logger->info('Запрос списка клиентов', [
                'limit' => $limit,
                'page' => $page,
                'filters' => $filters
            ]);
            
            $queryParams = [
                'limit' => $limit,
                'page' => $page
            ];
            
            // Добавляем фильтры в запрос
            foreach ($filters as $key => $value) {
                $queryParams[$key] = $value;
            }
            
            $customers = $this->apiClient->get('customers', $queryParams);
            
            $this->logger->info('Получен список клиентов', [
                'count' => count($customers['list'] ?? []),
                'total' => $customers['count'] ?? 0
            ]);
            
            return new JsonResponse($customers);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении списка клиентов', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении списка клиентов: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Получает информацию о клиенте
     *
     * @param Request $request HTTP запрос
     * @param int $id Идентификатор клиента
     * @return Response HTTP ответ
     */
    public function getCustomer(Request $request, int $id): Response
    {
        try {
            $this->logger->info('Запрос информации о клиенте', [
                'id' => $id
            ]);
            
            $customer = $this->apiClient->get('customers/' . $id);
            
            $this->logger->info('Получена информация о клиенте', [
                'id' => $id,
                'email' => $customer['email'] ?? 'Unknown'
            ]);
            
            return new JsonResponse($customer);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении информации о клиенте', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка при получении информации о клиенте: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Парсит фильтры из запроса
     *
     * @param Request $request HTTP запрос
     * @return array Массив фильтров
     */
    private function parseFilters(Request $request): array
    {
        $filters = [];
        $filterPrefix = 'filter_';
        
        foreach ($request->query->all() as $key => $value) {
            if (strpos($key, $filterPrefix) === 0) {
                $filterName = substr($key, strlen($filterPrefix));
                $filters[$filterName] = $value;
            }
        }
        
        return $filters;
    }
    
    /**
     * Индексирует все товары из Shoper в Trieve
     *
     * @param Request $request HTTP запрос
     * @return Response HTTP ответ
     */
    public function indexAllProducts(Request $request): Response
    {
        try {
            $page = 1;
            $limit = 50; // Получаем по 50 товаров за раз
            $totalIndexed = 0;
            $errors = [];

            do {
                // Получаем страницу товаров из Shoper API
                $products = $this->apiClient->get('products', [
                    'page' => $page,
                    'limit' => $limit
                ]);

                if (empty($products['list'])) {
                    break;
                }

                // Индексируем каждый товар в Trieve
                foreach ($products['list'] as $product) {
                    try {
                        if ($this->apiClient->indexProductInTrieve($product)) {
                            $totalIndexed++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'product_id' => $product['product_id'] ?? 'unknown',
                            'error' => $e->getMessage()
                        ];
                    }
                }

                $page++;

            } while (!empty($products['list']));

            return new JsonResponse([
                'success' => true,
                'total_indexed' => $totalIndexed,
                'errors' => $errors,
                'message' => "Индексация завершена. Обработано товаров: {$totalIndexed}"
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Ошибка при индексации товаров', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

