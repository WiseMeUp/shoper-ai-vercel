<?php

namespace ShoperAI\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;

class AdminController extends ControllerAbstract
{
    private array $config;

    private $authMiddleware;

    public function __construct(Logger $logger)
    {
        parent::__construct($logger);
        
        // Загружаем конфигурацию
        $this->config = [
            'app_id' => $_ENV['SHOPER_APP_ID'],
            'shop_url' => $_ENV['SHOPER_SHOP_URL'],
            'app_url' => $_ENV['SHOPER_APP_URL'],
            'admin_url' => $_ENV['SHOPER_APP_URL'] . '/admin',
        ];

        $this->logger->info('Загружена конфигурация AdminController', $this->config);
        
        // Инициализируем middleware для авторизации
        $this->authMiddleware = new \ShoperAI\Middleware\AuthMiddleware($logger);
    }

    /**
     * Отображает главную панель администратора
     */
    public function dashboard(Request $request): Response
    {
        // Проверяем авторизацию
        $authResponse = $this->authMiddleware->handle($request, function($request) {
            return null;
        });
        
        if ($authResponse !== null) {
            return $authResponse;
        }
        
        $this->logger->info('Запрос к панели администратора', [
            'shop_url' => $this->config['shop_url'],
            'app_id' => $this->config['app_id']
        ]);

        try {
            // В будущем здесь может быть логика загрузки данных для дашборда
            $dashboardData = [
                'title' => 'AI Search Dashboard',
                'appId' => $this->config['app_id'],
                'shopUrl' => $this->config['shop_url'],
                'statistics' => [
                    'searches' => 0,
                    'products' => 0,
                    'orders' => 0
                ]
            ];

            return $this->renderView('admin/dashboard', $dashboardData);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при загрузке панели администратора: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->renderView('error', [
                'message' => 'Произошла ошибка при загрузке панели администратора'
            ]);
        }
    }

    /**
     * Отображает страницу настроек
     */
    public function settings(Request $request): Response
    {
        // Проверяем авторизацию
        $authResponse = $this->authMiddleware->handle($request, function($request) {
            return null;
        });
        
        if ($authResponse !== null) {
            return $authResponse;
        }
        
        $this->logger->info('Запрос к странице настроек', [
            'shop_url' => $this->config['shop_url'],
            'app_id' => $this->config['app_id']
        ]);

        try {
            // В будущем здесь может быть логика загрузки текущих настроек из БД
            $settingsData = [
                'title' => 'AI Search Settings',
                'appId' => $this->config['app_id'],
                'shopUrl' => $this->config['shop_url'],
                'settings' => [
                    'enableAiSearch' => true,
                    'searchRelevance' => 0.8,
                    'maxResults' => 10,
                ]
            ];

            return $this->renderView('admin/settings', $settingsData);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при загрузке страницы настроек: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->renderView('error', [
                'message' => 'Произошла ошибка при загрузке страницы настроек'
            ]);
        }
    }
}

