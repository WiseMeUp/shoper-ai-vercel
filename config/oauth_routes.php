<?php

use ShoperAI\Controller\AuthController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

// OAuth routes
$routes->add('oauth_start', new Route('/oauth/start', [
    '_controller' => [AuthController::class, 'startOAuth']
]));

$routes->add('oauth_callback', new Route('/oauth/callback', [
    '_controller' => [AuthController::class, 'oauthCallback']
]));

return $routes;
