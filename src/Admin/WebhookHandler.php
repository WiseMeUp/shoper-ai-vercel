<?php

namespace ShoperAI\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebhookHandler
{
    public function handle(Request $request): JsonResponse
    {
        $event = $request->get('event');
        $payload = $request->get('payload');

        switch ($event) {
            case 'install':
                return $this->handleInstall($payload);
            case 'uninstall':
                return $this->handleUninstall($payload);
            case 'upgrade':
                return $this->handleUpgrade($payload);
            default:
                return new JsonResponse(['status' => 'error', 'message' => 'Unknown event'], 400);
        }
    }

    private function handleInstall(array $payload): JsonResponse
    {
        // Регистрация приложения в админке
        $appConfig = [
            'dashboard' => [
                'name' => 'Shoper AI Search',
                'url' => '/admin/dashboard',
                'icon' => 'fa-search',
                'menu' => [
                    'items' => [
                        [
                            'name' => 'Dashboard',
                            'url' => '/admin/dashboard'
                        ],
                        [
                            'name' => 'Settings',
                            'url' => '/admin/settings'
                        ]
                    ]
                ]
            ]
        ];

        return new JsonResponse([
            'status' => 'success',
            'app_config' => $appConfig
        ]);
    }

    private function handleUninstall(array $payload): JsonResponse
    {
        return new JsonResponse(['status' => 'success']);
    }

    private function handleUpgrade(array $payload): JsonResponse
    {
        return new JsonResponse(['status' => 'success']);
    }
}
