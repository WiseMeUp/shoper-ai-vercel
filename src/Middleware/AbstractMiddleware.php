<?php

namespace ShoperAI\Middleware;

use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Абстрактный класс для middleware
 * 
 * Предоставляет общую функциональность и логирование для всех middleware.
 */
abstract class AbstractMiddleware implements MiddlewareInterface
{
    /**
     * @var Logger Экземпляр логгера
     */
    protected Logger $logger;
    
    /**
     * Конструктор
     *
     * @param Logger $logger Экземпляр логгера
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Логирует информацию о запросе
     *
     * @param Request $request HTTP запрос
     * @param string $message Сообщение для логирования
     * @param array $context Дополнительный контекст
     * @return void
     */
    protected function logRequest(Request $request, string $message, array $context = []): void
    {
        $defaultContext = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
        ];
        
        $this->logger->info($message, array_merge($defaultContext, $context));
    }
    
    /**
     * Логирует ошибку запроса
     *
     * @param Request $request HTTP запрос
     * @param string $message Сообщение об ошибке
     * @param array $context Дополнительный контекст
     * @return void
     */
    protected function logError(Request $request, string $message, array $context = []): void
    {
        $defaultContext = [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
        ];
        
        $this->logger->error($message, array_merge($defaultContext, $context));
    }
}

