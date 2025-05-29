<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Определение маршрутов приложения
 */
$routes = new RouteCollection();

// Маршрут по умолчанию
$routes->add('index', new Route('/', [
    '_controller' => 'ShoperAI\Controller\Index::index'
]));

// Маршрут для проверки
$routes->add('check', new Route('/check', [
    '_controller' => 'ShoperAI\Controller\CheckController::check'
]));

// Маршруты для OAuth
$routes->add('oauth_callback', new Route('/oauth/callback', [
    '_controller' => 'ShoperAI\Controller\AuthController::callback'
]));

// Маршруты для административной панели
$routes->add('admin_dashboard', new Route('/admin/dashboard', [
    '_controller' => 'ShoperAI\Controller\AdminController::dashboard'
]));

$routes->add('admin_settings', new Route('/admin/settings', [
    '_controller' => 'ShoperAI\Controller\AdminController::settings'
]));

// Маршруты для webhooks
$routes->add('webhook_products', new Route('/webhook/products', [
    '_controller' => 'ShoperAI\Controller\WebhookController::products'
]));

$routes->add('webhook_orders', new Route('/webhook/orders', [
    '_controller' => 'ShoperAI\Controller\WebhookController::orders'
]));

// Маршруты для API
$routes->add('api_products', new Route('/api/products', [
    '_controller' => 'ShoperAI\Controller\ApiController::products'
]));

$routes->add('api_search', new Route('/api/search', [
    '_controller' => 'ShoperAI\Controller\ApiController::search'
]));

// Маршрут для обработки ошибок 404
$routes->add('notfound', new Route('/{path}', [
    '_controller' => 'ShoperAI\Controller\Index::notFound',
    'path' => null
], ['path' => '.*']));

return $routes;

