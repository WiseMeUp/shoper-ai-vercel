<?php

namespace ShoperAI\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use ShoperAI\Model\Trieve\Client as TrieveClient;

/**
 * Service for AI-powered search functionality using Trieve
 */
class AISearchService
{
    /**
     * @var ShoperApiClient The Shoper API client
     */
    private ShoperApiClient $apiClient;

    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var TrieveClient The Trieve client
     */
    private TrieveClient $trieve;

    /**
     * @var string The Trieve dataset ID
     */
    private string $datasetId;

    /**
     * @var Client The HTTP client for backup AI API
     */
    private Client $httpClient;

    /**
     * Constructor
     *
     * @param ShoperApiClient $apiClient The Shoper API client
     * @param LoggerInterface $logger The logger instance
     */
    public function __construct(
        ShoperApiClient $apiClient,
        LoggerInterface $logger
    ) {
        $this->apiClient = $apiClient;
        $this->logger = $logger;
        
        $this->datasetId = $_ENV['TRIEVE_DATASET_ID'] ?? '';
        
        $this->trieve = new TrieveClient($_ENV['TRIEVE_API_KEY'] ?? '');
        
        // Резервный клиент для OpenAI, если Trieve недоступен
        $this->httpClient = new Client([
            'base_uri' => $_ENV['AI_SEARCH_ENDPOINT'] ?? 'https://api.openai.com/v1/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . ($_ENV['AI_API_KEY'] ?? ''),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Index a product in Trieve for AI search
     *
     * @param array $product The product data to index
     * @return bool Success status
     * @throws \Exception If indexing fails
     */
    public function indexProduct(array $product): bool
    {
        try {
            $this->logger->info("Indexing product ID: {$product['product_id']}");
            
            $content = [
                'id' => $product['product_id'],
                'name' => $product['name'],
                'description' => $product['description'] ?? '',
                'categories' => is_array($product['categories'] ?? null) 
                    ? implode(', ', $product['categories']) 
                    : ($product['categories'] ?? ''),
                'price' => $product['price'] ?? 0,
                'sku' => $product['sku'] ?? '',
                'stock' => $product['stock'] ?? 0,
                'url' => $product['url'] ?? '',
                'images' => is_array($product['images'] ?? null) 
                    ? json_encode($product['images']) 
                    : ($product['images'] ?? ''),
                // Дополнительные поля
            ];
            
            return $this->trieve->index($this->datasetId, $content);
        } catch (\Exception $e) {
            $this->logger->error("Product indexing failed: " . $e->getMessage());
            throw new \Exception("Product indexing failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Perform AI-powered search on products using Trieve
     *
     * @param string $query The search query
     * @param array $filters Optional search filters
     * @return array The search results
     * @throws \Exception If search fails
     */
    public function searchProducts(string $query, array $filters = []): array
    {
        try {
            $this->logger->info("Performing AI search for query: {$query}");
            
            $searchParams = [
                'query' => $query,
                'filters' => $filters,
                'limit' => $filters['limit'] ?? 20,
                'page' => $filters['page'] ?? 1
            ];
            
            // Выполнение поиска через Trieve
            $searchResults = $this->trieve->search($this->datasetId, $searchParams);
            
            // Обогащаем результаты дополнительной информацией
            $enhancedResults = $this->enrichSearchResults($query, $searchResults['results'] ?? []);
            
            return [
                'query' => $query,
                'total_results' => $searchResults['total'] ?? count($searchResults['results'] ?? []),
                'results' => $enhancedResults,
                'search_metadata' => [
                    'enhanced_by_ai' => true,
                    'provider' => 'trieve',
                    'timestamp' => time(),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error("AI search failed: " . $e->getMessage());
            
            // Fallback к обычному поиску через Shoper API при сбое Trieve
            return $this->fallbackSearch($query, $filters);
        }
    }
    /**
     * Fallback search using Shoper API when Trieve is unavailable
     *
     * @param string $query The search query
     * @param array $filters The search filters
     * @return array The search results
     */
    private function fallbackSearch(string $query, array $filters = []): array
    {
        try {
            $this->logger->info("Falling back to standard Shoper search for query: {$query}");
            
            // Использование стандартного поиска Shoper API
            $params = array_merge([
                'limit' => $filters['limit'] ?? 20,
                'page' => $filters['page'] ?? 1,
                'search' => $query
            ], $filters);
            
            $response = $this->apiClient->get('products', $params);
            $products = $response['list'] ?? [];
            
            return [
                'query' => $query,
                'total_results' => $response['count'] ?? count($products),
                'results' => $products,
                'search_metadata' => [
                    'enhanced_by_ai' => false,
                    'provider' => 'shoper_api',
                    'timestamp' => time(),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error("Fallback search failed: " . $e->getMessage());
            return [
                'query' => $query,
                'total_results' => 0,
                'results' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Fetch products from Shoper API
     *
     * @param array $filters The product filters
     * @return array The products data
     * @throws \Exception If API request fails
     */
    private function fetchProductsFromShoper(array $filters = []): array
    {
        try {
            $params = array_merge([
                'limit' => 100, // Adjust as needed
                'page' => 1,
            ], $filters);
            
            $response = $this->apiClient->get('products', $params);
            
            return $response['list'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error("Failed to fetch products: " . $e->getMessage());
            throw new \Exception("Failed to fetch products: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Batch index multiple products
     *
     * @param array $products Array of products to index
     * @return array Result status for each product
     */
    public function batchIndexProducts(array $products): array
    {
        $results = [];
        
        foreach ($products as $product) {
            try {
                $success = $this->indexProduct($product);
                $results[$product['product_id']] = [
                    'success' => $success,
                    'error' => null
                ];
            } catch (\Exception $e) {
                $this->logger->error("Failed to index product {$product['product_id']}: " . $e->getMessage());
                $results[$product['product_id']] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    /**
     * Enhance search results with additional product details
     *
     * @param string $query The original search query
     * @param array $searchResults The search results from Trieve
     * @return array The enhanced search results
     */
    private function enrichSearchResults(string $query, array $searchResults): array
    {
        try {
            $this->logger->info("Enhancing search results with product details");
            
            // Обогащаем результаты дополнительными данными из Shoper API
            return array_map(function($result) {
                $productId = $result['id'] ?? null;
                
                if (!$productId) {
                    return $result;
                }
                
                try {
                    // Получаем детальную информацию о продукте
                    $productDetails = $this->apiClient->get("products/{$productId}");
                    
                    // Объединяем результат поиска с детальной информацией
                    return array_merge($result, [
                        'details' => $productDetails,
                        'score' => $result['score'] ?? 0,
                        'ai_enhanced' => true
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning("Failed to fetch details for product {$productId}: " . $e->getMessage());
                    return $result;
                }
            }, $searchResults);
        } catch (\Exception $e) {
            $this->logger->error("Failed to enhance search results: " . $e->getMessage());
            // В случае ошибки просто возвращаем исходные результаты
            return $searchResults;
        }
    }
    
    /**
     * Reindex all products from Shoper
     *
     * @param int $limit Maximum number of products to index
     * @return array Indexing statistics
     */
    public function reindexAllProducts(int $limit = 1000): array
    {
        $this->logger->info("Starting full reindexing of products");
        
        $stats = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        try {
            $page = 1;
            $pageSize = 100;
            $totalIndexed = 0;
            
            while ($totalIndexed < $limit) {
                $products = $this->fetchProductsFromShoper([
                    'limit' => $pageSize,
                    'page' => $page
                ]);
                
                if (empty($products)) {
                    break; // Нет больше продуктов
                }
                
                $indexResults = $this->batchIndexProducts($products);
                
                foreach ($indexResults as $productId => $result) {
                    $stats['total']++;
                    
                    if ($result['success']) {
                        $stats['success']++;
                    } else {
                        $stats['failed']++;
                        $stats['errors'][$productId] = $result['error'];
                    }
                }
                
                $totalIndexed += count($products);
                $page++;
                
                $this->logger->info("Indexed {$totalIndexed} products so far");
            }
            
            return $stats;
        } catch (\Exception $e) {
            $this->logger->error("Full reindexing failed: " . $e->getMessage());
            throw new \Exception("Full reindexing failed: " . $e->getMessage(), 0, $e);
        }
    }
}
