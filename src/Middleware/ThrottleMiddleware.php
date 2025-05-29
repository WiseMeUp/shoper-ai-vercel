<?php

namespace ShoperAI\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Monolog\Logger;

/**
 * Middleware для ограничения частоты запросов (rate limiting)
 */
class ThrottleMiddleware extends AbstractMiddleware
{
    /**
     * @var int Максимальное количество запросов за период
     */
    private int $limit;
    
    /**
     * @var int Период в секундах
     */
    private int $period;
    
    /**
     * @var FilesystemAdapter Адаптер кеша
     */
    private FilesystemAdapter $cache;
    
    /**
     * Конструктор
     *
     * @param Logger $logger Экземпляр логгера
     * @param int $limit Максимальное количество запросов
     * @param int $period Период в секундах
     */
    public function __construct(Logger $logger, int $limit = 60, int $period = 60)
    {
        parent::__construct($logger);
        $this->limit = $limit;
        $this->period = $period;
        $this->cache = new FilesystemAdapter('throttle', 0, __DIR__ . '/../../tmp/cache');
    }
    
    /**
     * Обрабатывает запрос и ограничивает частоту
     *
     * @param Request $request HTTP запрос
     * @param callable $next Следующий обработчик в цепочке
     * @return Response HTTP ответ
     */
    public function handle(Request $request, callable $next): Response
    {
        // Получаем ключ для идентификации клиента
        $key = $this->getClientKey($request);
        
        // Получаем текущие данные о количестве запросов
        $item = $this->cache->getItem($key);
        
        if (!$item->isHit()) {
            // Если это первый запрос, инициализируем счетчик
            $data = [
                'requests' => 1,
                'reset_time' => time() + $this->period
            ];
            $item->set($data);
            $item->expiresAfter($this->period);
            $this->cache->save($item);
        } else {
            // Получаем текущие данные
            $data = $item->get();
            
            // Проверяем, не истек ли период
            if (time() > $data['reset_time']) {
                // Если период истек, сбрасываем счетчик
                $data = [
                    'requests' => 1,
                    'reset_time' => time() + $this->period
                ];
            } else {
                // Увеличиваем счетчик запросов
                $data['requests']++;
            }
            
            // Если превышен лимит запросов
            if ($data['requests'] > $this->limit) {
                $resetSeconds = $data['reset_time'] - time();
                
                $this->logError($request, 'Превышен лимит запросов', [
                    'limit' => $this->limit,
                    'period' => $this->period,
                    'requests' => $data['requests'],
                    'reset_time' => date('Y-m-d H:i:s', $data['reset_time']),
                    'reset_seconds' => $resetSeconds
                ]);
                
                // Подготавливаем заголовки с информацией о лимитах
                $headers = [
                    'X-RateLimit-Limit' => $this->limit,
                    'X-RateLimit-Remaining' => 0,
                    'X-RateLimit-Reset' => $data['reset_time'],
                    'Retry-After' => $resetSeconds
                ];
                
                // Если запрос требует JSON-ответа
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new Response(
                        json_encode([
                            'error' => 'Too Many Requests',
                            'message' => 'Превышен лимит запросов. Пожалуйста, повторите попытку через ' . $resetSeconds . ' секунд.'
                        ]),
                        Response::HTTP_TOO_MANY_REQUESTS,
                        array_merge($headers, ['Content-Type' => 'application/json'])
                    );
                }
                
                // Если обычный запрос
                return new Response(
                    'Превышен лимит запросов. Пожалуйста, повторите попытку через ' . $resetSeconds . ' секунд.',
                    Response::HTTP_TOO_MANY_REQUESTS,
                    $headers
                );
            }
            
            // Сохраняем обновленные данные
            $item->set($data);
            $this->cache->save($item);
        }
        
        // Добавляем заголовки с информацией о лимитах
        $response = $next($request);
        $response->headers->add([
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => max(0, $this->limit - $data['requests']),
            'X-RateLimit-Reset' => $data['reset_time']
        ]);
        
        return $response;
    }
    
    /**
     * Получает уникальный ключ для идентификации клиента
     *
     * @param Request $request HTTP запрос
     * @return string Ключ клиента
     */
    private function getClientKey(Request $request): string
    {
        // Определяем ключ по IP-адресу и User-Agent
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent', '');
        $route = $request->attributes->get('_route', 'unknown');
        
        // В реальном приложении можно также использовать авторизационные данные пользователя
        // если он авторизован
        $user = $request->attributes->get('user', []);
        $userId = $user['id'] ?? 'guest';
        
        // Генерируем уникальный ключ
        $key = md5($ip . '_' . $route . '_' . $userId);
        
        return 'throttle_' . $key;
    }
}

