<?php

namespace KLP\KlpMcpServer\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Prompts Definition Compiler Pass
 *
 * Registers prompts in the container and configures the MCPServer with the prompt repository
 */
final class PromptsDefinitionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $prompts = $container->getparameter('klp_mcp_server.prompts');
        // Register each prompt as a service in the container if not already defined
        foreach ($prompts as $promptClass) {
            if (! $container->has($promptClass) && class_exists($promptClass)) {
                $definition = new Definition($promptClass);
                $definition->setAutowired(true);
                $definition->setPublic(true);
                $definition->addTag('klp_mcp_server.prompt');
                $container->setDefinition($promptClass, $definition);
            }
        }

        $server = $container->getDefinition('klp_mcp_server.server');
        $promptRepository = $container->getDefinition('klp_mcp_server.prompt_repository');
        $server->addMethodCall('registerPromptRepository', [$promptRepository]);
    }
}
