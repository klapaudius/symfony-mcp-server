<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Configures routes for the application using a custom route loader.
 *
 * @param  RoutingConfigurator  $routes  The routing configurator used to define routes.
 * @return void
 */
return function (RoutingConfigurator $routes) {
    // Use the custom route loader to conditionally load routes based on enabled providers
    $routes->import('.', 'mcp');
};
