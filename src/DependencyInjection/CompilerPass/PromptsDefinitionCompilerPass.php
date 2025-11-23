<?php

namespace KLP\KlpMcpServer\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Prompts Definition Compiler Pass
 *
 * Registers prompts from two sources:
 * 1. Static prompts defined in YAML configuration (klp_mcp_server.prompts)
 * 2. Dynamic prompts provided by PromptProviderInterface implementations
 *
 * This compiler pass also wires the PromptRepository to the MCPServer.
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

        // Discover and register prompt providers
        $promptRepository = $container->getDefinition('klp_mcp_server.prompt_repository');
        $taggedProviders = $container->findTaggedServiceIds('klp_mcp_server.prompt_provider');

        foreach ($taggedProviders as $providerId => $tags) {
            // Add a method call to register each provider with the PromptRepository
            $promptRepository->addMethodCall('registerProvider', [new Reference($providerId)]);
        }

        // Wire PromptRepository to MCPServer
        $server = $container->getDefinition('klp_mcp_server.server');
        $server->addMethodCall('registerPromptRepository', [$promptRepository]);
    }
}
