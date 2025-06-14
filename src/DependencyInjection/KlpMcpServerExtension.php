<?php

namespace KLP\KlpMcpServer\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class KlpMcpServerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration, $configs);
        $loader = new XmlFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('klp_mcp_server.enabled', $config['enabled']);
        $container->setParameter('klp_mcp_server.server.name', $config['server']['name']);
        $container->setParameter('klp_mcp_server.server.version', $config['server']['version']);
        $container->setParameter('klp_mcp_server.default_path', $config['default_path']);
        $container->setParameter('klp_mcp_server.ping.enabled', $config['ping']['enabled']);
        $container->setParameter('klp_mcp_server.ping.interval', $config['ping']['interval']);
        $providers = [];
        foreach ($config['server_providers'] as $serverProvider) {
            $providers[] = 'klp_mcp_server.provider.'.$serverProvider;
        }
        if (isset($config['server_provider'])
            && $config['server_provider'] === 'sse'
            && ! count($providers)) {
            $providers[] = 'klp_mcp_server.provider.sse';
        }
        $container->setParameter('klp_mcp_server.providers', $providers);
        $container->setParameter('klp_mcp_server.sse_adapter', $config['sse_adapter']);
        $container->setParameter('klp_mcp_server.adapters.redis.prefix', $config['adapters']['redis']['prefix'] ?? 'mcp_sse');
        $container->setParameter('klp_mcp_server.adapters.redis.host', $config['adapters']['redis']['host'] ?? 'default');
        $container->setParameter('klp_mcp_server.adapters.redis.ttl', $config['adapters']['redis']['ttl'] ?? 100);
        $container->setParameter('klp_mcp_server.adapters.cache.prefix', $config['adapters']['cache']['prefix'] ?? 'mcp_sse');
        $container->setParameter('klp_mcp_server.adapters.cache.ttl', $config['adapters']['cache']['ttl'] ?? 100);
        $container->setParameter('klp_mcp_server.tools', $config['tools']);

        // Set parameters for resources
        $container->setParameter('klp_mcp_server.resources', $config['resources'] ?? []);
        // Set parameters for resource templates
        $container->setParameter('klp_mcp_server.resources_templates', $config['resources_templates'] ?? []);

        // Conditionally remove controller services based on enabled providers
        $this->removeDisabledControllers($container, $providers);
    }

    private function removeDisabledControllers(ContainerBuilder $container, array $enabledProviders): void
    {
        // Remove SSE controllers if SSE provider is not enabled
        if (! in_array('klp_mcp_server.provider.sse', $enabledProviders, true)) {
            $container->removeDefinition('KLP\KlpMcpServer\Controllers\SseController');
            $container->removeAlias('klp_mcp_server.controller.sse');
            $container->removeDefinition('KLP\KlpMcpServer\Controllers\MessageController');
            $container->removeAlias('klp_mcp_server.controller.message');
        }

        // Remove StreamableHTTP controller if StreamableHTTP provider is not enabled
        if (! in_array('klp_mcp_server.provider.streamable_http', $enabledProviders, true)) {
            $container->removeDefinition('KLP\KlpMcpServer\Controllers\StreamableHttpController');
            $container->removeAlias('klp_mcp_server.controller.streamable_http');
        }
    }
}
