<?php

use KLP\KlpMcpServer\Controllers\MessageController;
use KLP\KlpMcpServer\Controllers\SseController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Configures routes for the application.
 *
 * @param  RoutingConfigurator  $routes  The routing configurator used to define routes.
 * @return void
 */
return function (RoutingConfigurator $routes) {
    $defaultPath = '%klp_mcp_server.default_path%';

    $routes->add('sse_route', "/$defaultPath/sse")
        ->controller([SseController::class, 'handle'])
        ->methods(['GET', 'POST']);

    $routes->add('message_route', "/$defaultPath/messages")
        ->controller([MessageController::class, 'handle'])
        ->methods(['POST']);
};
