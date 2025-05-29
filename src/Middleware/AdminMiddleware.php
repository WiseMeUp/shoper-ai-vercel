<?php

namespace ShoperAI\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Monolog\Logger;
use ShoperAI\Service\AuthenticationService;

/**
 * Middleware для проверки прав администратора
 */
class AdminMiddleware extends AbstractMiddleware
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
     * Обрабатывает запрос и проверяет права администратора
     *
     * @param Request $request HTTP запрос
     * @param callable $next Следующий обработчик в цепочке
     * @return Response HTTP ответ
     */
    public function handle(Request $request, callable $next): Response
    {
        // Проверяем права администратора
        if (!$this->authService->isAdmin()) {
            // Если запрос требует JSON-ответа
            if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                $this->logError($request, 'Попытка доступа к административным ресурсам без прав (AJAX/API)', [
                    'shop' => $this->authService->getShopUrl(),
                    'user_id' => $this->authService->getCurrentUser()['id'] ?? 'unknown'
                ]);
                
                return new Response(
                    json_encode(['error' => 'Forbidden', 'message' => 'Недостаточно прав для выполнения операции']),
                    Response::HTTP_FORBIDDEN,
                    ['Content-Type' => 'application/json']
                );
            }
            
            // Если обычный запрос - показываем сообщение об ошибке
            $this->logError($request, 'Попытка доступа к административным ресурсам без прав', [
                'shop' => $this->authService->getShopUrl(),
                'user_id' => $this->authService->getCurrentUser()['id'] ?? 'unknown'
            ]);
            
            // Перенаправляем на главную страницу с сообщением об ошибке
            $request->getSession()->getFlashBag()->add(
                'error',
                'У вас недостаточно прав для доступа к этому разделу.'
            );
            
            return new RedirectResponse('/');
        }
        
        $this->logRequest($request, 'Доступ к административному ресурсу', [
            'shop' => $this->authService->getShopUrl(),
            'user_id' => $this->authService->getCurrentUser()['id'] ?? 'unknown'
        ]);
        
        // Продолжаем обработку запроса
        return $next($request);
    }
}

