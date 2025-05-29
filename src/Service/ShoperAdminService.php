<?php

namespace ShoperAI\Service;

use DreamCommerce\ShopAppstoreLib\Client;

class ShoperAdminService
{
    public function registerInAdmin(string $shopUrl, string $accessToken): array
    {
        $client = new Client($shopUrl, $accessToken);
        
        return $client->apps->put([
            'app' => [
                'dashboard' => [
                    'title' => 'AI Search',
                    'url' => '/admin/dashboard',
                    'order' => 1
                ],
                'menu' => [
                    'title' => 'AI Search',
                    'url' => '/admin/dashboard',
                    'order' => 100,
                    'icon' => 'fa-search'
                ]
            ]
        ]);
    }
}
