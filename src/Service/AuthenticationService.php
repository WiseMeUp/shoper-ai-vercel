<?php

namespace ShoperAI\Service;

use DreamCommerce\ShopAppstoreLib\Client;
use DreamCommerce\ShopAppstoreLib\ClientInterface;
use DreamCommerce\ShopAppstoreLib\Handler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Сервис аутентификации для работы с OAuth в Shoper
 * 
 * Обеспечивает авторизацию через OAuth, управление токенами,
 * хранение информации о пользователе и проверку прав доступа.
 */
class AuthenticationService
{
    /**
     * @var string|null ID приложения
     */
    private ?string $appId;
    
    /**
     * @var string|null Секретный ключ приложения
     */
    private ?string $appSecret;
    
    /**
     * @var Logger Экземпляр логгера
     */
    private Logger $logger;
    
    /**
     * @var SessionInterface|null Интерфейс сессии
     */
    private ?SessionInterface $session;
    
    /**
     * @var ClientInterface|null Клиент API Shoper
     */
    private ?ClientInterface $client = null;
    
    /**
     * @var array Админские права в Shoper
     */
    private array $adminPermissions = ['admin', 'manage_store', 'api_admin'];
    
    /**
     * @var int Время жизни токена (в секундах)
     */
    private int $tokenLifetime = 3600;
    
    /**
     * @var int Максимальное время бездействия перед обновлением токена (в секундах)
     */
    private int $tokenRefreshThreshold = 600; // 10 минут
    
    /**
     * Конструктор
     *
     * @param Logger $logger Экземпляр логгера
     * @param string|null $appId ID приложения
     * @param string|null $appSecret Секретный ключ приложения
     * @param SessionInterface|null $session Интерфейс сессии
     */
    public function __construct(
        Logger $logger,
        ?string $appId = null,
        ?string $appSecret = null,
        ?SessionInterface $session = null
    ) {
        $this->logger = $logger;
        $this->appId = $appId ?? $_ENV['SHOPER_APP_ID'] ?? null;
        $this->appSecret = $appSecret ?? $_ENV['SHOPER_APP_SECRET'] ?? null;
        $this->session = $session;
        
        if (!$this->appId || !$this->appSecret) {
            $this->logger->warning('Не заданы учетные данные приложения (APP_ID и/или APP_SECRET)');
        }
    }
    
    /**
     * Инициирует процесс OAuth-авторизации
     *
     * @param string $shopUrl URL магазина
     * @param string $returnUrl URL для возврата после авторизации
     * @return string URL для перенаправления на авторизацию
     * @throws \Exception Если не удалось создать запрос на авторизацию
     */
    public function initiateOAuth(string $shopUrl, string $returnUrl): string
    {
        try {
            // Создаем экземпляр обработчика авторизации
            $handler = new Handler($this->appId, $this->appSecret, $returnUrl);
            
            // Нормализуем URL магазина
            $shopUrl = $this->normalizeShopUrl($shopUrl);
            
            // Генерируем URL для авторизации
            $authUrl = $handler->getAuthorizationUrl($shopUrl);
            
            // Сохраняем состояние в сессии
            $this->session?->set('oauth_shop_url', $shopUrl);
            $this->session?->set('oauth_return_url', $returnUrl);
            
            $this->logger->info('Инициирована OAuth авторизация', [
                'shop_url' => $shopUrl,
                'return_url' => $returnUrl
            ]);
            
            return $authUrl;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при инициировании OAuth авторизации', [
                'message' => $e->getMessage(),
                'shop_url' => $shopUrl,
                'return_url' => $returnUrl
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Обрабатывает OAuth callback от Shoper
     *
     * @param Request $request HTTP запрос
     * @return array Результат обработки callback
     * @throws \Exception Если произошла ошибка при обработке callback
     */
    public function handleOAuthCallback(Request $request): array
    {
        try {
            // Получаем параметры запроса
            $code = $request->query->get('code');
            $shopUrl = $this->session?->get('oauth_shop_url');
            $returnUrl = $this->session?->get('oauth_return_url');
            
            if (!$code || !$shopUrl) {
                throw new \Exception('Отсутствуют необходимые параметры для OAuth авторизации');
            }
            
            // Создаем экземпляр обработчика авторизации
            $handler = new Handler($this->appId, $this->appSecret, $returnUrl);
            
            // Получаем токены доступа
            $tokens = $handler->getAccessToken($shopUrl, $code);
            
            // Получаем информацию о пользователе
            $client = $this->createClient($shopUrl, $tokens['access_token']);
            $userInfo = $this->fetchUserInfo($client);
            
            // Сохраняем информацию в сессии
            $this->saveAuthDataToSession($shopUrl, $tokens, $userInfo);
            
            $this->logger->info('Успешная OAuth авторизация', [
                'shop_url' => $shopUrl,
                'user_id' => $userInfo['id'] ?? 'unknown'
            ]);
            
            return [
                'success' => true,
                'shop_url' => $shopUrl,
                'user_info' => $userInfo,
                'return_url' => $this->session?->get('return_url', '/admin')
            ];
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при обработке OAuth callback', [
                'message' => $e->getMessage(),
                'code' => $request->query->get('code'),
                'shop_url' => $this->session?->get('oauth_shop_url')
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Выполняет выход пользователя (удаление данных сессии)
     *
     * @return bool Результат операции
     */
    public function logout(): bool
    {
        if (!$this->session) {
            return false;
        }
        
        $shopUrl = $this->getShopUrl();
        $userId = $this->getCurrentUser()['id'] ?? 'unknown';
        
        // Удаляем данные аутентификации из сессии
        $this->session->remove('auth_shop_url');
        $this->session->remove('auth_tokens');
        $this->session->remove('auth_user_info');
        $this->session->remove('auth_last_activity');
        
        $this->logger->info('Пользователь вышел из системы', [
            'shop_url' => $shopUrl,
            'user_id' => $userId
        ]);
        
        return true;
    }
    
    /**
     * Проверяет, аутентифицирован ли пользователь
     *
     * @return bool Результат проверки
     */
    public function isAuthenticated(): bool
    {
        if (!$this->session) {
            return false;
        }
        
        $tokens = $this->session->get('auth_tokens');
        $shopUrl = $this->session->get('auth_shop_url');
        
        return !empty($tokens) && !empty($shopUrl);
    }
    
    /**
     * Проверяет, является ли пользователь администратором
     *
     * @return bool Результат проверки
     */
    public function isAdmin(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userInfo = $this->getCurrentUser();
        
        // Проверяем наличие админских привилегий
        if (isset($userInfo['permissions']) && is_array($userInfo['permissions'])) {
            foreach ($this->adminPermissions as $permission) {
                if (in_array($permission, $userInfo['permissions'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Получает URL магазина из сессии
     *
     * @return string|null URL магазина
     */
    public function getShopUrl(): ?string
    {
        return $this->session?->get('auth_shop_url');
    }
    
    /**
     * Получает информацию о текущем пользователе
     *
     * @return array Информация о пользователе
     */
    public function getCurrentUser(): array
    {
        return $this->session?->get('auth_user_info', []);
    }
    
    /**
     * Получает токен доступа
     *
     * @return string|null Токен доступа
     */
    public function getAccessToken(): ?string
    {
        $tokens = $this->session?->get('auth_tokens', []);
        return $tokens['access_token'] ?? null;
    }
    
    /**
     * Обновляет токен при необходимости
     *
     * @return bool Результат обновления
     */
    public function refreshSessionIfNeeded(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $lastActivity = $this->session?->get('auth_last_activity', 0);
        $currentTime = time();
        
        // Обновляем время последней активности
        $this->session?->set('auth_last_activity', $currentTime);
        
        // Если с момента последней активности прошло меньше порога обновления, не обновляем токен
        if (($currentTime - $lastActivity) < $this->tokenRefreshThreshold) {
            return true;
        }
        
        // Обновляем токен
        return $this->refreshToken();
    }
    
    /**
     * Обновляет токен доступа
     *
     * @return bool Результат обновления
     */
    public function refreshToken(): bool
    {
        try {
            $tokens = $this->session?->get('auth_tokens', []);
            $shopUrl = $this->session?->get('auth_shop_url');
            
            if (empty($tokens) || empty($shopUrl) || empty($tokens['refresh_token'])) {
                $this->logger->warning('Невозможно обновить токен: отсутствуют необходимые данные');
                return false;
            }
            
            // Создаем экземпляр обработчика авторизации
            $handler = new Handler($this->appId, $this->appSecret);
            
            // Обновляем токены
            $newTokens = $handler->refreshToken($shopUrl, $tokens['refresh_token']);
            
            // Обновляем информацию о пользователе
            $client = $this->createClient($shopUrl, $newTokens['access_token']);
            $userInfo = $this->fetchUserInfo($client);
            
            // Сохраняем обновленные данные в сессии
            $this->saveAuthDataToSession($shopUrl, $newTokens, $userInfo);
            
            $this->logger->info('Токен доступа успешно обновлен', [
                'shop_url' => $shopUrl,
                'user_id' => $userInfo['id'] ?? 'unknown'
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при обновлении токена доступа', [
                'message' => $e->getMessage(),
                'shop_url' => $this->session?->get('auth_shop_url')
            ]);
            
            // При ошибке обновления токена считаем сессию недействительной
            $this->logout();
            
            return false;
        }
    }
    
    /**
     * Создает клиент API Shoper
     *
     * @param string $shopUrl URL магазина
     * @param string $accessToken Токен доступа
     * @return ClientInterface Клиент API
     */
    public function createClient(string $shopUrl, string $accessToken): ClientInterface
    {
        $shopUrl = $this->normalizeShopUrl($shopUrl);
        $this->client = new Client($shopUrl, $accessToken);
        return $this->client;
    }
    
    /**
     * Получает текущий клиент API
     *
     * @return ClientInterface|null Клиент API
     */
    public function getClient(): ?ClientInterface
    {
        if ($this->client) {
            return $this->client;
        }
        
        $accessToken = $this->getAccessToken();
        $shopUrl = $this->getShopUrl();
        
        if (!$accessToken || !$shopUrl) {
            return null;
        }
        
        return $this->createClient($shopUrl, $accessToken);
    }
    
    /**
     * Получает информацию о пользователе из API
     *
     * @param ClientInterface $client Клиент API
     * @return array Информация о пользователе
     * @throws \Exception Если произошла ошибка при получении информации
     */
    private function fetchUserInfo(ClientInterface $client): array
    {
        try {
            $userResource = $client->resource('user');
            $userInfo = $userResource->get('account');
            
            // Получаем права пользователя
            $permissionsResource = $client->resource('user-permission');
            $permissions = $permissionsResource->get();
            
            // Добавляем права в информацию о пользователе
            $userInfo['permissions'] = array_column($permissions, 'name');
            
            return $userInfo;
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при получении информации о пользователе', [
                'message' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Сохраняет данные аутентификации в сессии
     *
     * @param string $shopUrl URL магазина
     * @param array $tokens Токены доступа
     * @param array $userInfo Информация о пользователе
     * @return void
     */
    private function saveAuthDataToSession(string $shopUrl, array $tokens, array $userInfo): void
    {
        if (!$this->session) {
            return;
        }
        
        $this->session->set('auth_shop_url', $shopUrl);
        $this->session->set('auth_tokens', $tokens);
        $this->session->set('auth_user_info', $userInfo);
        $this->session->set('auth_last_activity', time());
    }
    
    /**
     * Нормализует URL магазина
     *
     * @param string $shopUrl URL магазина
     * @return string Нормализованный URL
     */
    private function normalizeShopUrl(string $shopUrl): string
    {
        // Убираем завершающий слеш
        $shopUrl = rtrim($shopUrl, '/');
        
        // Добавляем схему, если отсутствует
        if (!preg_match('~^https?://~i', $shopUrl)) {
            $shopUrl = 'https://' . $shopUrl;
        }
        
        return $shopUrl;
    }
}
