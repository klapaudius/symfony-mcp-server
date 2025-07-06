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

        $server = $container->getDefinition('klp_mcp_server.server');
        $toolRepository = $container->getDefinition('klp_mcp_server.tool_repository');
        $server->addMethodCall('registerToolRepository', [$toolRepository]);
        
        // Register the sampling response handler if a sampling client is available
        $server->addMethodCall('registerSamplingResponseHandler', []);
    }
}
