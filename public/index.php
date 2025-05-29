<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use ShoperAI\Controller\AuthController;
use ShoperAI\Controller\CheckController;
use ShoperAI\Admin\Controllers\AdminController;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Загружаем переменные окружения
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Создаем логгер
$logger = new Logger('app');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG));

try {
    // Создаем объект запроса
    $request = Request::createFromGlobals();

    // Получаем путь запроса
    $path = $request->getPathInfo();

    // Маршрутизация
    switch ($path) {
        case '/':
        case '/oauth/start':
            $controller = new AuthController($logger);
            $response = $controller->start($request);
            break;

        case '/oauth/callback':
            $controller = new AuthController($logger);
            $response = $controller->callback($request);
            break;

        case '/check':
            $controller = new CheckController($logger);
            $response = $controller->check($request);
            break;

        case '/admin':
            // Простая проверка авторизации
            if (!isset($_SESSION['authorized']) || $_SESSION['authorized'] !== true) {
                $logger->info('Неавторизованный доступ к админ-панели');
                $response = new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
            } else {
                $controller = new AdminController($logger);
                $response = $controller->index($request);
            }
            break;

        default:
            $response = new JsonResponse([
                'status' => 'error',
                'message' => 'Route not found'
            ], 404);
    }

    // Отправляем ответ
    $response->send();

} catch (\Exception $e) {
    // Логируем ошибку
    $logger->error('Unexpected error: ' . $e->getMessage());

    // Отправляем ответ с ошибкой
    $response = new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage()
    ], 500);
    $response->send();
}
