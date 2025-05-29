<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use DreamCommerce\ShopAppstoreLib\Client;
use DreamCommerce\ShopAppstoreLib\Handler;

try {
    // Получаем параметры установки из запроса
    $shopUrl = $_GET['shop'] ?? null;
    $appToken = $_GET['token'] ?? null;
    $appSecret = $_ENV['SHOPER_APPSTORE_SECRET'] ?? null;

    if (!$shopUrl || !$appToken || !$appSecret) {
        throw new Exception('Отсутствуют необходимые параметры установки');
    }

    // Проверяем подпись
    if (!hash_equals(hash('sha512', $shopUrl . $appSecret), $appToken)) {
        throw new Exception('Неверная подпись установки');
    }

    // Создаем handler для установки
    $handler = new Handler($_ENV['SHOPER_APP_ID'], $_ENV['SHOPER_APP_SECRET']);
    
    // Устанавливаем приложение
    $installResponse = $handler->install($shopUrl, [
        // Список необходимых разрешений
        'admin',
        'admin_products',
        'admin_categories',
        'admin_configuration'
    ]);

    // Отправляем успешный ответ
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'app_url' => $_ENV['SHOPER_APP_URL'] . '/admin',
        'message' => 'Приложение успешно установлено'
    ]);

} catch (Exception $e) {
    // В случае ошибки
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
