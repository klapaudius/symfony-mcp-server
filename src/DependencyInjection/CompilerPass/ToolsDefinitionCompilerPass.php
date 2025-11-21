<?php

namespace KLP\KlpMcpServer\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tools Definition Compiler Pass
 *
 * Registers tools from two sources:
 * 1. Static tools defined in YAML configuration (klp_mcp_server.tools)
 * 2. Dynamic tools provided by ToolProviderInterface implementations
 *
 * This compiler pass also wires the ToolRepository to the MCPServer.
 */
final class ToolsDefinitionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $tools = $container->getparameter('klp_mcp_server.tools');

        // Register each tool as a service in the container if not already defined
        foreach ($tools as $toolClass) {
            if (! $container->has($toolClass) && class_exists($toolClass)) {
                $definition = new Definition($toolClass);
                $definition->setAutowired(true);
                $definition->setPublic(true);
                $definition->addTag('klp_mcp_server.tool');
                $container->setDefinition($toolClass, $definition);
            }
        }

        // Discover and register tool providers
        $toolRepository = $container->getDefinition('klp_mcp_server.tool_repository');
        $taggedProviders = $container->findTaggedServiceIds('klp_mcp_server.tool_provider');

        foreach ($taggedProviders as $providerId => $tags) {
            // Add a method call to register each provider with the ToolRepository
            $toolRepository->addMethodCall('registerProvider', [new Reference($providerId)]);
        }

        // Wire ToolRepository to MCPServer
        $server = $container->getDefinition('klp_mcp_server.server');
        $server->addMethodCall('registerToolRepository', [$toolRepository]);

        // Register the sampling response handler if a sampling client is available
        $server->addMethodCall('registerSamplingResponseHandler', []);
    }
}
