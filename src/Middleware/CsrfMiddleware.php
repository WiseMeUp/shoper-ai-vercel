<?php

namespace ShoperAI\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Monolog\Logger;

/**
 * Middleware для защиты от CSRF-атак
 */
class CsrfMiddleware extends AbstractMiddleware
{
    /**
     * @var SessionInterface Интерфейс сессии
     */
    private SessionInterface $session;
    
    /**
     * @var string Имя токена в сессии
     */
    private string $tokenName;
    
    /**
     * @var int Срок жизни токена в секундах
     */
    private int $tokenLifetime;
    
    /**
     * Конструктор
     *
     * @param Logger $logger Экземпляр логгера
     * @param SessionInterface $session Интерфейс сессии
     * @param string $tokenName Имя токена в сессии
     * @param int $tokenLifetime Срок жизни токена в секундах
     */
    public function __construct(
        Logger $logger,
        SessionInterface $session,
        string $tokenName = 'csrf_token',
        int $tokenLifetime = 3600
    ) {
        parent::__construct($logger);
        $this->session = $session;
        $this->tokenName = $tokenName;
        $this->tokenLifetime = $tokenLifetime;
    }
    
    /**
     * Обрабатывает запрос и проверяет CSRF-токен
     *
     * @param Request $request HTTP запрос
     * @param callable $next Следующий обработчик в цепочке
     * @return Response HTTP ответ
     */
    public function handle(Request $request, callable $next): Response
    {
        // Проверяем только POST, PUT, DELETE и PATCH запросы
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = null;
            
            // Получаем токен из запроса
            if ($request->headers->has('X-CSRF-Token')) {
                $token = $request->headers->get('X-CSRF-Token');
            } elseif ($request->request->has('csrf_token')) {
                $token = $request->request->get('csrf_token');
            }
            
            // Проверяем токен
            if (!$this->validateToken($token)) {
                $this->logError($request, 'Неверный CSRF-токен', [
                    'token_received' => $token,
                    'token_expected' => $this->session->get($this->tokenName)
                ]);
                
                // Если запрос требует JSON-ответа
                if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json') {
                    return new Response(
                        json_encode(['error' => 'Invalid CSRF token', 'message' => 'Недействительный CSRF-токен']),
                        Response::HTTP_FORBIDDEN,
                        ['Content-Type' => 'application/json']
                    );
                }
                
                // Если обычный запрос - показываем сообщение об ошибке
                $request->getSession()->getFlashBag()->add(
                    'error',
                    'Ошибка безопасности: недействительный CSRF-токен. Пожалуйста, попробуйте еще раз.'
                );
                
                return new Response('Forbidden: Invalid CSRF token', Response::HTTP_FORBIDDEN);
            }
        }
        
        // Генерируем новый токен для следующего запроса
        $this->generateToken();
        
        // Устанавливаем токен в атрибуты запроса для доступа в контроллере
        $request->attributes->set('csrf_token', $this->session->get($this->tokenName));
        
        // Продолжаем обработку запроса
        return $next($request);
    }
    
    /**
     * Проверяет CSRF-токен
     *
     * @param string|null $token Токен для проверки
     * @return bool Результат проверки
     */
    private function validateToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        $storedToken = $this->session->get($this->tokenName);
        $storedTokenTime = $this->session->get($this->tokenName . '_time', 0);
        
        // Проверяем наличие токена и его срок действия
        if (empty($storedToken) || (time() - $storedTokenTime) > $this->tokenLifetime) {
            return false;
        }
        
        // Сравниваем токены безопасным способом
        return hash_equals($storedToken, $token);
    }
    
    /**
     * Генерирует новый CSRF-токен и сохраняет его в сессии
     *
     * @return string Сгенерированный токен
     */
    private function generateToken(): string
    {
        // Генерируем случайный токен
        $token = bin2hex(random_bytes(32));
        
        // Сохраняем токен и время его создания в сессии
        $this->session->set($this->tokenName, $token);
        $this->session->set($this->tokenName . '_time', time());
        
        return $token;
    }
}

