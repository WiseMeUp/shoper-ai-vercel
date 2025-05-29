<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\HttpFoundation\Session\Session;
use ShoperAI\Service\AuthenticationService;
use ShoperAI\Model\AdminSettings;

function getServices(): array
{
    // Настраиваем логгер
    $logger = new Logger('app');
    $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

    // Создаем сессию (но не стартуем её)
    $session = new Session();

    // Инициализируем сервис аутентификации
    $authService = new AuthenticationService($logger);

    // Инициализируем настройки админки
    $adminSettings = new AdminSettings();

    return [
        'logger' => $logger,
        'session' => $session,
        'authService' => $authService,
        'adminSettings' => $adminSettings
    ];
}

// Загружаем переменные окружения
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

return getServices();
