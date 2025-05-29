<?php

namespace ShoperAI\Service\Search;

use Psr\Log\LoggerInterface;

class TrieveSearchService implements SearchServiceInterface
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function indexProduct(array $product): bool
    {
        $this->logger->info('TrieveSearchService::indexProduct called but is deprecated');
        return false;
    }

    public function searchProducts(string $query, array $filters = []): array
    {
        $this->logger->info('TrieveSearchService::searchProducts called but is deprecated');
        return [
            'query' => $query,
            'total_results' => 0,
            'results' => [],
            'search_metadata' => [
                'provider' => 'trieve_deprecated',
                'timestamp' => time(),
                'message' => 'Trieve search is deprecated, please use ElasticSearch'
            ],
        ];
    }

    public function batchIndexProducts(array $products): array
    {
        $this->logger->info('TrieveSearchService::batchIndexProducts called but is deprecated');
        return array_map(function($product) {
            return [
                'id' => $product['product_id'] ?? null,
                'success' => false,
                'error' => 'Trieve search is deprecated'
            ];
        }, $products);
    }
}

