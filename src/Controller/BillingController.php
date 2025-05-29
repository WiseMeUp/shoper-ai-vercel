<?php

namespace ShoperAI\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Monolog\Logger;

class BillingController
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function register(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'success',
            'app' => [
                'billing' => [
                    'subscription' => false,
                    'type' => 'free'
                ],
                'dashboard' => [
                    'url' => '/admin',
                    'order' => 100,
                    'title' => 'AI Search'
                ],
                'menu' => [
                    'url' => '/admin',
                    'order' => 100,
                    'title' => 'AI Search',
                    'icon' => 'fa-search'
                ]
            ]
        ]);
    }
}
