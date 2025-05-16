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
        $supportedAdapters = ['redis'];
        $adapterPrefix = 'klp_mcp_server.adapter.';
        $supportedAdaptersServices = array_map(function ($item) use ($adapterPrefix) {
            return $adapterPrefix.$item;
        }, $supportedAdapters);
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

                // Middleware Configuration
            ->arrayNode('middlewares')
            ->prototype('scalar')
            ->end()
            ->end()

                // Server Provider
            ->scalarNode('server_provider')
            ->defaultValue('sse')
            ->cannotBeEmpty()
            ->validate()
            ->ifNotInArray(['sse'])
            ->thenInvalid('The server provider "%s" is not supported. Currently only "sse" is supported.')
            ->end()
            ->end()

                // SSE Adapter
            ->scalarNode('sse_adapter')
            ->defaultValue('redis')
            ->cannotBeEmpty()
            ->validate()
            ->ifNotInArray($supportedAdaptersServices)
            ->thenInvalid('The sse adapter "%s" is not supported. Please choose one of '.implode(', ', $supportedAdaptersServices))
            ->end()
            ->end()

                // Adapters for SSE
            ->arrayNode('adapters')
            ->useAttributeAsKey('name') // Allows keys like "in_memory" and "redis"
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

                // Prompts
            ->arrayNode('prompts')
            ->prototype('scalar')
            ->end()
            ->end()

                // Resources
            ->arrayNode('resources')
            ->prototype('scalar')
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
