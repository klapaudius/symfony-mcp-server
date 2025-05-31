<?php

namespace KLP\KlpMcpServer\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Server-Sent Events Service Provider
 *
 * Registers the MCPServer as a singleton when server_provider config is set to "sse"
 */
final class ResourcesDefinitionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $resources = $container->getparameter('klp_mcp_server.resources');
        // Register each resource as a service in the container if not already defined
        foreach ($resources as $resourceClass) {
            if (! $container->has($resourceClass) && class_exists($resourceClass)) {
                $definition = new Definition($resourceClass);
                $definition->setAutowired(true);
                $definition->setPublic(true);
                $definition->addTag('klp_mcp_server.resource');
                $container->setDefinition($resourceClass, $definition);
            }
        }

        $resourceTemplates = $container->getparameter('klp_mcp_server.resources_templates');
        // Register each resource as a service in the container if not already defined
        foreach ($resourceTemplates as $resourceTemplateClass) {
            if (! $container->has($resourceTemplateClass) && class_exists($resourceTemplateClass)) {
                $definition = new Definition($resourceTemplateClass);
                $definition->setAutowired(true);
                $definition->setPublic(true);
                $definition->addTag('klp_mcp_server.resource_template');
                $container->setDefinition($resourceTemplateClass, $definition);
            }
        }

        $server = $container->getDefinition('klp_mcp_server.server');
        $resourceRepository = $container->getDefinition('klp_mcp_server.resource_repository');
        $server->addMethodCall('registerResourceRepository', [$resourceRepository]);
    }
}
