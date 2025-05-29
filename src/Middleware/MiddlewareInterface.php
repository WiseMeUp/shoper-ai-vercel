<?php

namespace ShoperAI\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Интерфейс для middleware компонентов
 */
interface MiddlewareInterface
{
    /**
     * Обрабатывает запрос и либо возвращает ответ, либо передает обработку следующему обработчику
     *
     * @param Request $request HTTP запрос
     * @param callable $next Следующий обработчик в цепочке
     * @return Response HTTP ответ
     */
    public function handle(Request $request, callable $next): Response;
}

