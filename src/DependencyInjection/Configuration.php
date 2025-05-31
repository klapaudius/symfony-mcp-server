<?php

namespace KLP\KlpMcpServer\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('klp_mcp_server');
        $rootNode = $treeBuilder->getRootNode();
        $supportedAdapters = ['cache', 'redis'];
        $supportedAdaptersServices = array_map(function ($item) {
            return $item;
        }, $supportedAdapters);
        $supportedProviders = ['streamable_http', 'sse'];
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                // MCP Server Activation
            ->booleanNode('enabled')
            ->defaultTrue()
            ->end()

                // Server Information
            ->arrayNode('server')
            ->children()
            ->scalarNode('name')
            ->defaultValue('KLP MCP Server')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('version')
            ->defaultValue('0.1.0')
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end()

                // MCP Default Path
            ->scalarNode('default_path')
            ->defaultValue('mcp')
            ->cannotBeEmpty()
            ->end()

                // ping feature
            ->arrayNode('ping')
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->integerNode('interval')
            ->defaultValue(60)
            ->end()
            ->end()
            ->end()

            // Server Providers
            ->arrayNode('server_providers')
            ->prototype('scalar')
            ->defaultValue(['streamable_http', 'sse'])
            ->cannotBeEmpty()
            ->validate()
            ->ifNotInArray($supportedProviders)
            ->thenInvalid('The server provider "%s" is not supported. Please choose one of '.implode(', ', $supportedProviders))
            ->end()
            ->end()
            ->end()

                // Server Provider
            ->scalarNode('server_provider')
            ->setDeprecated('klapaudius/symfony-mcp-server', '1.2', 'The "server_provider" option is deprecated since version 1.2 and will be removed in 2.0. Use "server_providers" instead.')
            ->end()

                // SSE Adapter
            ->scalarNode('sse_adapter')
            ->defaultValue('cache')
            ->cannotBeEmpty()
            ->validate()
            ->ifNotInArray($supportedAdaptersServices)
            ->thenInvalid('The sse adapter "%s" is not supported. Please choose one of '.implode(', ', $supportedAdaptersServices))
            ->end()
            ->end()

                // Adapters for SSE
            ->arrayNode('adapters')
            ->useAttributeAsKey('name') // Allows keys like "cache" and "redis"
            ->arrayPrototype()
            ->children()
            ->scalarNode('prefix')
            ->defaultValue('mcp_sse_')
            ->end()
            ->scalarNode('host')
            ->defaultValue('default')
            ->end()
            ->integerNode('ttl')
            ->defaultValue(100)
            ->end()
            ->end()
            ->end()
            ->end()

                // Tools List
            ->arrayNode('tools')
            ->prototype('scalar')
            ->validate()
            ->ifTrue(static fn ($v) => ! class_exists($v))
            ->thenInvalid('The tool "%s" must be a valid fully qualified class name.')
            ->end()
            ->end()
            ->end()

                // Prompts Comming Next
//            ->arrayNode('prompts')
//            ->prototype('scalar')
//            ->end()
//            ->end()

                // Resources
            ->arrayNode('resources')
            ->prototype('scalar')
            ->validate()
            ->ifTrue(static fn ($v) => ! class_exists($v))
            ->thenInvalid('The resource "%s" must be a valid fully qualified class name.')
            ->end()
            ->end()
            ->end()

                // Resources Templates Providers
            ->arrayNode('resources_templates')
            ->prototype('scalar')
            ->validate()
            ->ifTrue(static fn ($v) => ! class_exists($v))
            ->thenInvalid('The resource "%s" must be a valid fully qualified class name.')
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
