<?php

namespace ShoperAI\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Monolog\Logger;

class AuthMiddleware
{
    private Logger $logger;
    private array $config;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->config = [
            'app_id' => $_ENV['SHOPER_APP_ID'],
            'shop_url' => $_ENV['SHOPER_SHOP_URL'],
        ];
    }

    /**
     * Проверяет авторизацию перед выполнением контроллера
     */
    public function handle(Request $request, callable $next): Response
    {
        // Проверяем сессию авторизации
        if (!$this->isAuthorized($request)) {
            $this->logger->warning('Попытка несанкционированного доступа к админке', [
                'ip' => $request->getClientIp(),
                'path' => $request->getPathInfo(),
                'shop_url' => $this->config['shop_url']
            ]);

            // Если это AJAX запрос, возвращаем JSON с ошибкой
            if ($request->isXmlHttpRequest()) {
                return new Response(
                    json_encode([
                        'status' => 'error',
                        'message' => 'Unauthorized access',
                        'redirect' => '/oauth/start'
                    ]),
                    401,
                    ['Content-Type' => 'application/json']
                );
            }

            // В противном случае перенаправляем на страницу авторизации
            return new RedirectResponse('/oauth/start');
        }

        // Если авторизация успешна, продолжаем обработку запроса
        return $next($request);
    }

    /**
     * Проверяет, авторизован ли пользователь
     */
    private function isAuthorized(Request $request): bool
    {
        // В реальном приложении здесь должна быть проверка сессии,
        // токена авторизации и других параметров безопасности
        $session = $request->getSession();
        
        // Проверяем наличие access_token в сессии
        if ($session && $session->has('access_token')) {
            return true;
        }
        
        // Для временного тестирования, считаем запросы с localhost авторизованными
        // В продакшене это нужно удалить
        if ($request->getClientIp() === '127.0.0.1' || $request->getClientIp() === '::1') {
            return true;
        }
        
        // Для запросов из iframe от Shoper, проверяем заголовки
        $referer = $request->headers->get('Referer');
        if ($referer && strpos($referer, $this->config['shop_url']) !== false) {
            return true;
        }
        
        return false;
    }
}

<?php

namespace ShoperAI\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Monolog\Logger;
use ShoperAI\Service\AuthenticationService;

/**
 * Middleware для проверки аутентификации пользователя
 */
class AuthMiddleware extends AbstractMiddleware
{
    /**
     * @var AuthenticationService Сервис аутентификации
     */
    private AuthenticationService $authService;
    
    /**
     * Конструктор
     *
     * @param Logger $logger Экземпляр логгера
     * @param AuthenticationService $authService Сервис аутентификации
     */
    public function __construct(Logger $logger, AuthenticationService $authService)
    {
        parent::__construct($logger);
        $this->authService = $authService;
    }
    
    /**
     * Обрабатывает запрос и проверяет аутентификацию пользователя
     *
     * @param Request $request HTTP запрос
     * @param callable $next Следующий обработчик в цепочке
     * @return Response HTTP ответ
     */
    public function handle(Request $request, callable $next): Response
    {
        // Проверяем аутентификацию
        if (!$this->authService->isAuthenticated()) {
            // Если запрос требует JSON-ответа
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                $this->logError($request, 'Неавторизованный доступ к защищенному ресурсу (AJAX/API)');
                
                return new Response(
                    json_encode(['error' => 'Unauthorized', 'message' => 'Необходима авторизация']),
                    Response::HTTP_UNAUTHORIZED,
                    ['Content-Type' => 'application/json']
                );
            }
            
            // Если обычный запрос - перенаправляем на страницу авторизации
            $this->logError($request, 'Неавторизованный доступ к защищенному ресурсу');
            
            // Сохраняем URL для возврата после авторизации
            $returnUrl = $request->getRequestUri();
            if ($returnUrl !== '/') {
                $request->getSession()->set('return_url', $returnUrl);
            }
            
            return new RedirectResponse('/oauth/start');
        }
        
        // Обновляем данные в сессии при необходимости
        $this->authService->refreshSessionIfNeeded();
        
        // Добавляем информацию об авторизованном пользователе в запрос
        $request->attributes->set('user', $this->authService->getCurrentUser());
        $request->attributes->set('shop', $this->authService->getShopUrl());
        
        $this->logRequest($request, 'Доступ к защищенному ресурсу', [
            'shop' => $this->authService->getShopUrl(),
            'user_id' => $this->authService->getCurrentUser()['id'] ?? 'unknown'
        ]);
        
        // Продолжаем обработку запроса
        return $next($request);
    }
}

