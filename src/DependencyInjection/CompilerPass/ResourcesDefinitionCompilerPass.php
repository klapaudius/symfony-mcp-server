<?php

namespace KLP\KlpMcpServer\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Resources Definition Compiler Pass
 *
 * Registers resources from two sources:
 * 1. Static resources defined in YAML configuration (klp_mcp_server.resources)
 * 2. Dynamic resources provided by ResourceProviderInterface implementations
 *
 * This compiler pass also wires the ResourceRepository to the MCPServer.
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

        // Discover and register resource providers
        $resourceRepository = $container->getDefinition('klp_mcp_server.resource_repository');
        $taggedProviders = $container->findTaggedServiceIds('klp_mcp_server.resource_provider');

        foreach ($taggedProviders as $providerId => $tags) {
            // Add a method call to register each provider with the ResourceRepository
            $resourceRepository->addMethodCall('registerProvider', [new Reference($providerId)]);
        }

        // Wire ResourceRepository to MCPServer
        $server = $container->getDefinition('klp_mcp_server.server');
        $server->addMethodCall('registerResourceRepository', [$resourceRepository]);
    }
}
