<?php

namespace ShoperAI\Service\Search;

use Psr\Log\LoggerInterface;

/**
 * Парсер поисковых запросов для извлечения структурированных параметров
 */
class QueryParser
{
    /**
     * @var array Конфигурация парсера
     */
    private array $config;

    /**
     * @var LoggerInterface Логгер
     */
    private LoggerInterface $logger;

    /**
     * Конструктор
     *
     * @param array $config Конфигурация
     * @param LoggerInterface $logger Логгер
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Парсинг поискового запроса
     *
     * @param string $query Поисковый запрос
     * @return array Структурированные параметры запроса
     */
    public function parse(string $query): array
    {
        $this->logger->debug("Parsing query: {$query}");
        
        $result = [
            'original_query' => $query,
            'cleaned_query' => $query,
            'parameters' => [],
        ];
        
        // Проверяем, включено ли распознавание параметров
        if (!isset($this->config['smart_search']['parameter_recognition']['enabled']) ||
            !$this->config['smart_search']['parameter_recognition']['enabled']) {
            return $result;
        }
        
        // Получаем настройки распознавания параметров
        $parameterConfig = $this->config['smart_search']['parameter_recognition']['parameters'] ?? [];
        
        // Извлекаем параметры из запроса
        foreach ($parameterConfig as $paramType => $config) {
            $parameterValue = $this->extractParameter($query, $config);
            
            if ($parameterValue) {
                $result['parameters'][$paramType] = $parameterValue;
                
                // Удаляем найденный параметр из запроса для дальнейшего анализа
                $query = $this->removeParameterFromQuery($query, $parameterValue, $config);
            }
        }
        
        // Обновляем очищенный запрос (без извлеченных параметров)
        $result['cleaned_query'] = trim($query);
        
        $this->logger->debug("Parsed query result: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Извлечение параметра из запроса
     *
     * @param string $query Поисковый запрос
     * @param array $config Конфигурация параметра
     * @return mixed Извлеченное значение или null
     */
    private function extractParameter(string $query, array $config)
    {
        // Проверяем, используются ли регулярные выражения
        $isRegex = $config['is_regex'] ?? false;
        $patterns = $config['patterns'] ?? [];
        $prefixes = $config['prefix'] ?? [];
        
        // Поиск по регулярным выражениям
        if ($isRegex) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $query, $matches)) {
                    // Извлекаем значение из первой группы (если есть)
                    return count($matches) > 1 ? $matches[1] : $matches[0];
                }
            }
        } else {
            // Поиск по точному совпадению
            foreach ($patterns as $pattern) {
                if (stripos($query, $pattern) !== false) {
                    return $pattern;
                }
            }
            
            // Поиск по префиксам (например, "цвет: красный")
            foreach ($prefixes as $prefix) {
                $prefixPattern = "/{$prefix}[\s:]+([^\s,]+)/i";
                if (preg_match($prefixPattern, $query, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Удаление найденного параметра из запроса
     *
     * @param string $query Поисковый запрос
     * @param string $paramValue Значение параметра
     * @param array $config Конфигурация параметра
     * @return string Запрос без параметра
     */
    private function removeParameterFromQuery(string $query, string $paramValue, array $config): string
    {
        $prefixes = $config['prefix'] ?? [];
        
        // Удаляем параметр вместе с префиксом, если он есть
        foreach ($prefixes as $prefix) {
            $patterns = [
                "/{$prefix}[\s:]+{$paramValue}/i",
                "/{$paramValue}[\s,]+{$prefix}/i",
            ];
            
            foreach ($patterns as $pattern) {
                $query = preg_replace($pattern, '', $query);
            }
        }
        
        // Удаляем сам параметр
        $query = str_ireplace($paramValue, '', $query);
        
        // Убираем лишние пробелы и запятые
        $query = preg_replace('/\s+/', ' ', $query);
        $query = preg_replace('/,\s*,/', ',', $query);
        $query = trim($query, " ,");
        
        return $query;
    }
    
    /**
     * Преобразование разобранного запроса в фильтры для ElasticSearch
     *
     * @param array $parsedQuery Разобранный запрос
     * @return array Фильтры для поиска
     */
    public function buildSearchFilters(array $parsedQuery): array
    {
        $filters = [];
        $parameters = $parsedQuery['parameters'] ?? [];
        
        // Преобразование параметров в фильтры
        foreach ($parameters as $paramType => $value) {
            switch ($paramType) {
                case 'brand':
                    $filters['brand'] = $value;
                    break;
                
                case 'color':
                    $filters['colors'] = $value;
                    break;
                
                case 'size':
                    // Извлекаем числовое значение размера
                    if (preg_match('/\d+/', $value, $matches)) {
                        $filters['sizes'] = $matches[0];
                    }
                    break;
                
                case 'price':
                    // Обработка разных форматов цены
                    if (preg_match('/до\s*(\d+)/i', $value, $matches)) {
                        $filters['price_max'] = (float)$matches[1];
                    } elseif (preg_match('/от\s*(\d+)/i', $value, $matches)) {
                        $filters['price_min'] = (float)$matches[1];
                    } elseif (preg_match('/(\d+)-(\d+)/i', $value, $matches)) {
                        $filters['price_min'] = (float)$matches[1];
                        $filters['price_max'] = (float)$matches[2];
                    }
                    break;
            }
        }
        
        return $filters;
    }
}

