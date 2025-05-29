<?php

namespace ShoperAI\Service\Search;

interface SearchServiceInterface
{
    /**
     * Индексация продукта
     *
     * @param array $product Данные продукта
     * @return bool Успех операции
     */
    public function indexProduct(array $product): bool;

    /**
     * Поиск продуктов
     *
     * @param string $query Поисковый запрос
     * @param array $filters Фильтры поиска
     * @return array Результаты поиска
     */
    public function searchProducts(string $query, array $filters = []): array;

    /**
     * Пакетная индексация продуктов
     *
     * @param array $products Массив продуктов
     * @return array Результаты индексации
     */
    public function batchIndexProducts(array $products): array;
}

