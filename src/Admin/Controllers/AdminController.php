<?php

namespace ShoperAI\Admin\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AdminController
{
    private Logger $logger;
    private ?SessionInterface $session;

    public function __construct(
        Logger $logger,
        ?SessionInterface $session = null
    ) {
        $this->logger = $logger;
        $this->session = $session;
    }

    public function index(Request $request): Response
    {
        // Проверяем авторизацию
        if (!$this->session || !$this->session->get('auth_tokens')) {
            $this->logger->info('Неавторизованный доступ к админ-панели');
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $shopUrl = $this->session->get('auth_shop_url');
        $userInfo = $this->session->get('auth_user_info', []);
        $email = isset($userInfo['email']) ? $userInfo['email'] : 'Нет данных';

        $this->logger->info('Доступ к админ-панели', [
            'shop_url' => $shopUrl,
            'user' => $userInfo
        ]);

        return new Response($this->renderAdminPanel([
            'shop_url' => $shopUrl,
            'email' => $email
        ]));
    }

    private function renderAdminPanel(array $data): string
    {
        $shopUrl = htmlspecialchars($data['shop_url']);
        $email = htmlspecialchars($data['email']);

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AI Search - Панель управления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/admin">
                <i class="fas fa-search"></i> AI Search
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Информация о магазине</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>URL магазина:</strong> {$shopUrl}</p>
                        <p><strong>Пользователь:</strong> {$email}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }
}
