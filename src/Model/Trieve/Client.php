<?php

namespace ShoperAI\Model\Trieve;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Client for interacting with Trieve API
 */
class Client
{
    /**
     * @var HttpClient The HTTP client
     */
    private HttpClient $httpClient;

    /**
     * @var string The API key
     */
    private string $apiKey;

    /**
     * @var string The API base URL
     */
    private string $baseUrl;

    /**
     * Constructor
     *
     * @param string $apiKey The API key
     * @param string|null $baseUrl Optional base URL
     */
    public function __construct(string $apiKey, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl ?? 'https://api.trieve.ai/api/v1/';

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Index a document in a dataset
     *
     * @param string $datasetId The dataset ID
     * @param array $content The content to index
     * @return bool Success status
     * @throws \Exception
     */
    public function index(string $datasetId, array $content): bool
    {
        try {
            $response = $this->httpClient->post("datasets/{$datasetId}/chunks", [
                'json' => [
                    'content' => json_encode($content),
                    'metadata' => [
                        'id' => $content['id'] ?? null,
                        'source' => 'shoper_product',
                    ],
                ],
            ]);

            $statusCode = $response->getStatusCode();
            return $statusCode >= 200 && $statusCode < 300;
        } catch (GuzzleException $e) {
            throw new \Exception("Failed to index document: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Search a dataset
     *
     * @param string $datasetId The dataset ID
     * @param array $params The search parameters
     * @return array The search results
     * @throws \Exception
     */
    public function search(string $datasetId, array $params): array
    {
        try {
            $response = $this->httpClient->post("datasets/{$datasetId}/search", [
                'json' => [
                    'query' => $params['query'] ?? '',
                    'filters' => $params['filters'] ?? [],
                    'page_size' => $params['limit'] ?? 10,
                    'page' => $params['page'] ?? 1,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse API response: " . json_last_error_msg());
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new \Exception("Search failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a document from a dataset
     *
     * @param string $datasetId The dataset ID
     * @param string $documentId The document ID
     * @return bool Success status
     * @throws \Exception
     */
    public function deleteDocument(string $datasetId, string $documentId): bool
    {
        try {
            $response = $this->httpClient->delete("datasets/{$datasetId}/chunks/{$documentId}");

            $statusCode = $response->getStatusCode();
            return $statusCode >= 200 && $statusCode < 300;
        } catch (GuzzleException $e) {
            throw new \Exception("Failed to delete document: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new dataset
     *
     * @param string $name The dataset name
     * @return array The created dataset
     * @throws \Exception
     */
    public function createDataset(string $name): array
    {
        try {
            $response = $this->httpClient->post("dataset/create", [
                'json' => [
                    'name' => $name,
                    'organization_id' => '48d78893-c8fb-4ab8-a78a-9ce51e381d80',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse API response: " . json_last_error_msg());
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new \Exception("Failed to create dataset: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a list of datasets
     *
     * @return array The datasets
     * @throws \Exception
     */
    public function getDatasets(): array
    {
        try {
            $response = $this->httpClient->get("dataset/get_all");

            $data = json_decode((string) $response->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse API response: " . json_last_error_msg());
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new \Exception("Failed to get datasets: " . $e->getMessage(), 0, $e);
        }
    }
}

