<?php

namespace ShoperAI\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Monolog\Logger;

class CheckController
{
    private Logger $logger;
    private array $config;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        
        // Загружаем конфигурацию
        $this->config = [
            'app_id' => $_ENV['SHOPER_APP_ID'],
            'shop_url' => $_ENV['SHOPER_SHOP_URL'],
            'ngrok_url' => $_ENV['SHOPER_APP_URL'],
            'admin_url' => $_ENV['SHOPER_APP_URL'] . '/admin',
            'oauth_callback' => $_ENV['OAUTH_REDIRECT_URI']
        ];

        $this->logger->info('Загружена конфигурация', $this->config);
    }

    public function check(Request $request): JsonResponse
    {
        try {
            $this->logger->info('Проверка настроек приложения', [
                'shop_url' => $this->config['shop_url'],
                'app_id' => $this->config['app_id'],
                'oauth_callback' => $this->config['oauth_callback']
            ]);

            return new JsonResponse([
                'status' => 'success',
                'config' => $this->config
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при проверке настроек: ' . $e->getMessage(), [
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
