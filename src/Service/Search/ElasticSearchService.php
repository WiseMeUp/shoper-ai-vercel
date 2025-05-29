<?php

namespace ShoperAI\Service\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Psr\Log\LoggerInterface;
use ShoperAI\Service\ShoperApiClient;

/**
 * Сервис для поиска с использованием ElasticSearch
 */
class ElasticSearchService implements SearchServiceInterface
{
    private Client $client;
    private LoggerInterface $logger;
    private ShoperApiClient $apiClient;
    private array $config;
    private string $indexName = 'products';
    private QueryParser $queryParser;

    public function __construct(
        LoggerInterface $logger,
        ShoperApiClient $apiClient,
        array $config,
        ?Client $client = null
    ) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->config = $config;
        $this->queryParser = new QueryParser($config, $logger);

        $this->initClient($client);
    }

    private function initClient(?Client $client = null): void
    {
        if ($client) {
            $this->client = $client;
            return;
        }

        $connectionConfig = $this->config['connections']['default'] ?? [];
        
        $clientBuilder = ClientBuilder::create()
            ->setHosts($connectionConfig['hosts'] ?? ['localhost:9200'])
            ->setLogger($this->logger);

        if (!empty($connectionConfig['username']) && !empty($connectionConfig['password'])) {
            $clientBuilder->setBasicAuthentication(
                $connectionConfig['username'],
                $connectionConfig['password']
            );
        }

        if (!empty($connectionConfig['ssl']['enabled']) && $connectionConfig['ssl']['enabled']) {
            $clientBuilder->setSSLVerification($connectionConfig['ssl']['verify'] ?? true);
        }

        $this->client = $clientBuilder->build();
    }

    public function createIndicesIfNotExist(): bool
    {
        try {
            $exists = $this->client->indices()->exists([
                'index' => $this->indexName
            ])->asBool();

            if (!$exists) {
                $this->logger->info("Creating products index in ElasticSearch");
                
                $this->client->indices()->create([
                    'index' => $this->indexName,
                    'body' => [
                        'settings' => [
                            'analysis' => [
                                'filter' => [
                                    'polish_stop' => [
                                        'type' => 'stop',
                                        'stopwords' => '_polish_'
                                    ]
                                ],
                                'analyzer' => [
                                    'polish_analyzer' => [
                                        'type' => 'custom',
                                        'tokenizer' => 'standard',
                                        'filter' => [
                                            'lowercase',
                                            'polish_stop',
                                            'asciifolding'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'mappings' => $this->getProductMapping()
                    ]
                ]);
                
                $this->logger->info("Products index created successfully");
            }
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create ElasticSearch indices: " . $e->getMessage());
            return false;
        }
    }

    private function getProductMapping(): array
    {
        return [
            'properties' => [
                'product_id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'polish_analyzer',
                    'fields' => [
                        'keyword' => ['type' => 'keyword']
                    ]
                ],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'polish_analyzer'
                ],
                'price' => ['type' => 'double'],
                'price_range' => ['type' => 'keyword'],
                'brand' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword']
                    ]
                ],
                'categories' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword']
                    ]
                ],
                'tags' => [
                    'type' => 'text',
                    'fields' => [
                        'keyword' => ['type' => 'keyword']
                    ]
                ],
                'attributes' => [
                    'type' => 'nested',
                    'properties' => [
                        'name' => ['type' => 'keyword'],
                        'value' => ['type' => 'keyword']
                    ]
                ],
                'colors' => ['type' => 'keyword'],
                'sizes' => ['type' => 'keyword'],
                'stock_status' => ['type' => 'keyword'],
                'stock' => ['type' => 'integer'],
                'popularity_score' => ['type' => 'float'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
                'sku' => ['type' => 'keyword'],
                'tax_id' => ['type' => 'integer'],
                'currency' => ['type' => 'keyword'],
                'manufacturer_id' => ['type' => 'integer'],
                'unit' => ['type' => 'keyword'],
                'stock_weight' => ['type' => 'float'],
                'search_data' => [
                    'type' => 'text',
                    'analyzer' => 'polish_analyzer'
                ]
            ]
        ];
    }

    public function indexProduct(array $product): bool
    {
        try {
            $this->logger->info("Indexing product ID: {$product['product_id']}");
            
            $document = $this->transformProductToDocument($product);
            
            $response = $this->client->index([
                'index' => $this->indexName,
                'id' => $document['product_id'],
                'body' => $document,
                'refresh' => true // Делаем документ сразу доступным для поиска
            ])->asArray();

            return $response['result'] === 'created' || $response['result'] === 'updated';
        } catch (\Exception $e) {
            $this->logger->error("Product indexing failed: " . $e->getMessage(), [
                'product_id' => $product['product_id'] ?? 'unknown',
                'exception' => $e
            ]);
            return false;
        }
    }

    public function searchProducts(string $query, array $filters = []): array
    {
        try {
            $this->logger->info("Performing search with query: {$query}", ['filters' => $filters]);
            
            // Парсим поисковый запрос
            $parsedQuery = $this->queryParser->parse($query);
            
            // Формируем запрос к ElasticSearch
            $searchParams = [
                'index' => $this->indexName,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'multi_match' => [
                                        'query' => $parsedQuery['cleaned_query'],
                                        'fields' => [
                                            'name^3',
                                            'brand^2',
                                            'description',
                                            'tags',
                                            'search_data'
                                        ],
                                        'type' => 'best_fields',
                                        'operator' => 'and',
                                        'fuzziness' => 'AUTO'
                                    ]
                                ]
                            ],
                            'filter' => []
                        ]
                    ],
                    'sort' => [
                        ['popularity_score' => ['order' => 'desc']],
                        '_score'
                    ],
                    'size' => $filters['limit'] ?? 20,
                    'from' => isset($filters['page']) ? ($filters['page'] - 1) * ($filters['limit'] ?? 20) : 0,
                    '_source' => true,
                    'highlight' => [
                        'fields' => [
                            'name' => new \stdClass(),
                            'description' => new \stdClass()
                        ]
                    ],
                    'aggs' => $this->getDefaultAggregations()
                ]
            ];

            // Добавляем фильтры
            $this->addFilters($searchParams['body']['query']['bool'], $parsedQuery['parameters'], $filters);

            // Выполняем поиск
            $response = $this->client->search($searchParams)->asArray();
            
            return $this->formatSearchResults($response, $query);
        } catch (\Exception $e) {
            $this->logger->error("Search failed: " . $e->getMessage(), [
                'query' => $query,
                'filters' => $filters
            ]);
            
            return [
                'query' => $query,
                'total_results' => 0,
                'results' => [],
                'aggregations' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    public function batchIndexProducts(array $products): array
    {
        $results = [];
        $operations = [];

        foreach ($products as $product) {
            try {
                $document = $this->transformProductToDocument($product);
                
                // Добавляем операции для bulk indexing
                $operations[] = [
                    'index' => [
                        '_index' => $this->indexName,
                        '_id' => $document['product_id']
                    ]
                ];
                $operations[] = $document;
                
                $results[$product['product_id']] = [
                    'status' => 'pending',
                    'error' => null
                ];
            } catch (\Exception $e) {
                $results[$product['product_id']] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($operations)) {
            try {
                $response = $this->client->bulk([
                    'body' => $operations,
                    'refresh' => true
                ])->asArray();

                foreach ($response['items'] as $item) {
                    $id = $item['index']['_id'];
                    $results[$id]['status'] = $item['index']['result'];
                    if (isset($item['index']['error'])) {
                        $results[$id]['error'] = $item['index']['error']['reason'];
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error("Bulk indexing failed: " . $e->getMessage());
                
                foreach ($results as &$result) {
                    if ($result['status'] === 'pending') {
                        $result['status'] = 'error';
                        $result['error'] = 'Bulk operation failed: ' . $e->getMessage();
                    }
                }
            }
        }

        return $results;
    }

    private function transformProductToDocument(array $product): array
    {
        // Подготавливаем поисковые данные
        $searchData = implode(' ', [
            $product['name'] ?? '',
            $product['description'] ?? '',
            $product['brand'] ?? '',
            implode(' ', $product['categories'] ?? []),
            implode(' ', $product['tags'] ?? [])
        ]);

        return [
            'product_id' => (string)$product['product_id'],
            'name' => $product['name'] ?? '',
            'description' => $product['description'] ?? '',
            'price' => (float)($product['price'] ?? 0),
            'price_range' => $this->getPriceRange((float)($product['price'] ?? 0)),
            'brand' => $product['brand'] ?? '',
            'categories' => $product['categories'] ?? [],
            'tags' => $product['tags'] ?? [],
            'attributes' => $this->prepareAttributes($product['attributes'] ?? []),
            'colors' => $product['colors'] ?? [],
            'sizes' => $product['sizes'] ?? [],
            'stock_status' => ($product['stock'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
            'stock' => (int)($product['stock'] ?? 0),
            'popularity_score' => (float)($product['popularity_score'] ?? 0),
            'created_at' => $product['created_at'] ?? date('c'),
            'updated_at' => $product['updated_at'] ?? date('c'),
            'sku' => $product['sku'] ?? '',
            'tax_id' => (int)($product['tax_id'] ?? 0),
            'currency' => $product['currency'] ?? 'PLN',
            'manufacturer_id' => (int)($product['manufacturer_id'] ?? 0),
            'unit' => $product['unit'] ?? 'szt',
            'stock_weight' => (float)($product['stock_weight'] ?? 0),
            'search_data' => $searchData
        ];
    }

    private function prepareAttributes(array $attributes): array
    {
        $prepared = [];
        foreach ($attributes as $attribute) {
            if (is_array($attribute)) {
                $prepared[] = [
                    'name' => $attribute['name'] ?? '',
                    'value' => $attribute['value'] ?? ''
                ];
            }
        }
        return $prepared;
    }

    private function getPriceRange(float $price): string
    {
        if ($price <= 100) return 'do 100 PLN';
        if ($price <= 500) return '100-500 PLN';
        if ($price <= 1000) return '500-1000 PLN';
        if ($price <= 5000) return '1000-5000 PLN';
        return 'powyżej 5000 PLN';
    }

    private function addFilters(array &$boolQuery, array $parameters, array $filters): void
    {
        // Добавляем фильтры из параметров запроса
        foreach ($parameters as $key => $value) {
            switch ($key) {
                case 'price':
                    if (isset($value['min'])) {
                        $boolQuery['filter'][] = ['range' => ['price' => ['gte' => $value['min']]]];
                    }
                    if (isset($value['max'])) {
                        $boolQuery['filter'][] = ['range' => ['price' => ['lte' => $value['max']]]];
                    }
                    break;
                case 'brand':
                    $boolQuery['filter'][] = ['term' => ['brand.keyword' => $value]];
                    break;
                case 'color':
                    $boolQuery['filter'][] = ['term' => ['colors' => $value]];
                    break;
                case 'size':
                    $boolQuery['filter'][] = ['term' => ['sizes' => $value]];
                    break;
            }
        }

        // Добавляем фильтры из внешних параметров
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($value !== null && $value !== '') {
                    switch ($key) {
                        case 'price_min':
                            $boolQuery['filter'][] = ['range' => ['price' => ['gte' => (float)$value]]];
                            break;
                        case 'price_max':
                            $boolQuery['filter'][] = ['range' => ['price' => ['lte' => (float)$value]]];
                            break;
                        case 'stock_status':
                            $boolQuery['filter'][] = ['term' => ['stock_status' => $value]];
                            break;
                        case 'categories':
                            $boolQuery['filter'][] = ['terms' => ['categories.keyword' => (array)$value]];
                            break;
                        case 'in_stock':
                            if ($value) {
                                $boolQuery['filter'][] = ['term' => ['stock_status' => 'in_stock']];
                            }
                            break;
                    }
                }
            }
        }
    }

    private function formatSearchResults(array $response, string $query): array
    {
        $hits = array_map(function($hit) {
            return array_merge(
                $hit['_source'],
                [
                    'score' => $hit['_score'],
                    'highlight' => $hit['highlight'] ?? []
                ]
            );
        }, $response['hits']['hits']);

        return [
            'query' => $query,
            'total_results' => $response['hits']['total']['value'],
            'results' => $hits,
            'aggregations' => $response['aggregations'] ?? [],
            'search_metadata' => [
                'took' => $response['took'],
                'timed_out' => $response['timed_out'],
                'provider' => 'elasticsearch'
            ]
        ];
    }

    private function getDefaultAggregations(): array
    {
        return [
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['to' => 100],
                        ['from' => 100, 'to' => 500],
                        ['from' => 500, 'to' => 1000],
                        ['from' => 1000, 'to' => 5000],
                        ['from' => 5000]
                    ]
                ]
            ],
            'brands' => [
                'terms' => [
                    'field' => 'brand.keyword',
                    'size' => 10
                ]
            ],
            'categories' => [
                'terms' => [
                    'field' => 'categories.keyword',
                    'size' => 10
                ]
            ],
            'attributes' => [
                'nested' => [
                    'path' => 'attributes'
                ],
                'aggs' => [
                    'names' => [
                        'terms' => [
                            'field' => 'attributes.name',
                            'size' => 10
                        ],
                        'aggs' => [
                            'values' => [
                                'terms' => [
                                    'field' => 'attributes.value',
                                    'size' => 10
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}

