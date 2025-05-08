<?php

use KLP\KlpMcpServer\Controllers\MessageController;
use KLP\KlpMcpServer\Controllers\SseController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->add('sse_route', '/mcp/sse')
        ->controller([SseController::class, 'handle'])
        ->methods(['GET']);

    $routes->add('message_route', '/mcp/message')
        ->controller([MessageController::class, 'handle'])
        ->methods(['POST']);
};
