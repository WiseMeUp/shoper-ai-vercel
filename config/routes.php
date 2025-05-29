<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use ShoperAI\Controller\AuthController;
use ShoperAI\Admin\Controllers\AdminController;
use ShoperAI\Controller\CheckController;

$routes = new RouteCollection();

// OAuth routes
$routes->add('oauth_start', new Route('/oauth/start', [
    '_controller' => [AuthController::class, 'startOAuth']
]));

$routes->add('oauth_callback', new Route('/oauth/callback', [
    '_controller' => [AuthController::class, 'oauthCallback']
]));

// Admin routes
$routes->add('admin', new Route('/admin', [
    '_controller' => [AdminController::class, 'index']
]));

$routes->add('admin_dashboard', new Route('/admin/dashboard', [
    '_controller' => [AdminController::class, 'index']
]));

// Check route (для диагностики)
$routes->add('check', new Route('/check', [
    '_controller' => [CheckController::class, 'check']
]));

return $routes;
