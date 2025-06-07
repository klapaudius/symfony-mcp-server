<?php

use KLP\KlpMcpServer\Controllers\MessageController;
use KLP\KlpMcpServer\Controllers\SseController;
use KLP\KlpMcpServer\Controllers\StreamableHttpController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Configures routes for the application.
 *
 * @param  RoutingConfigurator  $routes  The routing configurator used to define routes.
 * @return void
 */
return function (RoutingConfigurator $routes) {
    $defaultPath = '%klp_mcp_server.default_path%';

    // SSE (Procole version: 2024-11-05)
    $routes->add('sse_route', "/$defaultPath/sse")
        ->controller([SseController::class, 'handle'])
        ->methods(['GET', 'POST']);

    $routes->add('message_route', "/$defaultPath/messages")
        ->controller([MessageController::class, 'handle'])
        ->methods(['POST']);

    // Streamable Http (Procole version: 2025-03-26)
    $routes->add('streamable_http_route', "/$defaultPath")
        ->controller([StreamableHttpController::class, 'handle'])
        ->methods(['GET', 'POST']);
};
