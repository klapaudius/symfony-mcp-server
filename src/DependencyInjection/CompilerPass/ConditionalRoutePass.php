<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollection;

/**
 * Compiler pass to conditionally register routes based on enabled server providers
 */
class ConditionalRoutePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('klp_mcp_server.providers')) {
            return;
        }

        $enabledProviders = $container->getParameter('klp_mcp_server.providers');
        $defaultPath = $container->getParameter('klp_mcp_server.default_path');
        $routeLoader = $container->findDefinition('klp_mcp_server.route_loader');

        // Pass the enabled providers and default path to the route loader
        $routeLoader->addMethodCall('setEnabledProviders', [$enabledProviders]);
        $routeLoader->addMethodCall('setDefaultPath', [$defaultPath]);
    }
}
