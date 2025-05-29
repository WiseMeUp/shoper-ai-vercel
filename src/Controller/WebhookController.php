<?php

namespace ShoperAI\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Monolog\Logger;

class WebhookController extends ControllerAbstract
{
    private array $config;
    private string $webhookSecret;

    public function __construct(Logger $logger)
    {
        parent::__construct($logger);
        
        // Загружаем конфигурацию
        $this->config = [
            'app_id' => $_ENV['SHOPER_APP_ID'],
            'shop_url' => $_ENV['SHOPER_SHOP_URL'],
        ];
        
        // В реальном приложении этот секрет должен храниться в базе данных
        // и соответствовать секрету, настроенному в админке Shoper
        $this->webhookSecret = $_ENV['SHOPER_APPSTORE_SECRET'] ?? '';

        $this->logger->info('Загружена конфигурация WebhookController', $this->config);
    }

    /**
     * Проверяет подпись webhook для обеспечения безопасности
     */
    private function validateWebhook(Request $request): bool
    {
        $signature = $request->headers->get('X-Shop-Signature');
        $content = $request->getContent();
        
        if (empty($signature) || empty($content)) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $content, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Обрабатывает webhook для обновления продуктов
     */
    public function products(Request $request): JsonResponse
    {
        $this->logger->info('Получен webhook products', [
            'shop_url' => $this->config['shop_url'],
            'remote_ip' => $request->getClientIp()
        ]);

        try {
            // Проверяем подпись webhook
            if (!$this->validateWebhook($request)) {
                $this->logger->warning('Неверная подпись webhook для products', [
                    'shop_url' => $this->config['shop_url'],
                    'remote_ip' => $request->getClientIp()
                ]);
                
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid webhook signature'
                ], 403);
            }

            // Получаем данные webhook
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid JSON payload'
                ], 400);
            }

            // Обрабатываем данные о продукте
            // В реальном приложении здесь должна быть логика обновления продуктов
            $this->logger->info('Обработка webhook products', [
                'product_id' => $data['product_id'] ?? 'unknown',
                'event' => $data['event'] ?? 'unknown'
            ]);
            
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Product webhook processed successfully'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при обработке webhook products: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Обрабатывает webhook для обновления заказов
     */
    public function orders(Request $request): JsonResponse
    {
        $this->logger->info('Получен webhook orders', [
            'shop_url' => $this->config['shop_url'],
            'remote_ip' => $request->getClientIp()
        ]);

        try {
            // Проверяем подпись webhook
            if (!$this->validateWebhook($request)) {
                $this->logger->warning('Неверная подпись webhook для orders', [
                    'shop_url' => $this->config['shop_url'],
                    'remote_ip' => $request->getClientIp()
                ]);
                
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid webhook signature'
                ], 403);
            }

            // Получаем данные webhook
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid JSON payload'
                ], 400);
            }

            // Обрабатываем данные о заказе
            // В реальном приложении здесь должна быть логика обработки заказов
            $this->logger->info('Обработка webhook orders', [
                'order_id' => $data['order_id'] ?? 'unknown',
                'event' => $data['event'] ?? 'unknown'
            ]);
            
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Order webhook processed successfully'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при обработке webhook orders: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }
}

