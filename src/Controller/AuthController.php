<?php

namespace ShoperAI\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Monolog\Logger;

class AuthController
{
    private Logger $logger;
    private $appId;
    private $appSecret;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->appId = $_ENV['SHOPER_APP_ID'];
        $this->appSecret = $_ENV['SHOPER_APP_SECRET'];
    }

    public function start(Request $request): RedirectResponse
    {
        try {
            // Удаляем "https://" из URL магазина, если он есть
            $shopUrl = $_ENV['SHOPER_SHOP_URL'];
            $shopUrl = str_replace('https://', '', $shopUrl);
            
            $this->logger->info('Инициирована OAuth авторизация', [
                'shop_url' => $shopUrl,
                'app_id' => $this->appId
            ]);

            // Создаем URL для OAuth авторизации
            $params = [
                'response_type' => 'code',
                'client_id' => $this->appId,
                'redirect_uri' => $_ENV['OAUTH_REDIRECT_URI']
            ];

            $authUrl = sprintf(
                'https://%s/admin/oauth2/authorize?%s',
                $shopUrl,
                http_build_query($params)
            );
            
            $this->logger->info('Редирект на авторизацию', [
                'auth_url' => $authUrl
            ]);

            return new RedirectResponse($authUrl);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка OAuth авторизации: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new RedirectResponse('/error?message=' . urlencode($e->getMessage()));
        }
    }

    public function callback(Request $request): JsonResponse
    {
        try {
            $code = $request->query->get('code');
            $shopUrl = str_replace('https://', '', $_ENV['SHOPER_SHOP_URL']);

            if (empty($code)) {
                throw new \Exception('Не получен код авторизации');
            }

            $this->logger->info('Получен код авторизации', [
                'shop_url' => $shopUrl,
                'has_code' => true
            ]);

            // Запрос на получение токена
            $params = [
                'grant_type' => 'authorization_code',
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'code' => $code,
                'redirect_uri' => $_ENV['OAUTH_REDIRECT_URI']
            ];

            $tokenUrl = sprintf('https://%s/admin/oauth2/token', $shopUrl);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tokenUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }
            
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception('Ошибка получения токена: ' . $response);
            }

            $token = json_decode($response, true);

            if (!isset($token['access_token'])) {
                throw new \Exception('Токен не получен в ответе');
            }

            // Сохраняем в сессии
            $_SESSION['authorized'] = true;
            $_SESSION['token'] = $token['access_token'];
            $_SESSION['shop_url'] = $shopUrl;

            $this->logger->info('Успешная OAuth авторизация', [
                'shop_url' => $shopUrl,
                'has_token' => true
            ]);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Авторизация успешна',
                'redirect_to' => '/admin'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка OAuth callback: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
