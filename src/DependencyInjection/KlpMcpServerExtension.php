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
        $container->setParameter('klp_mcp_server.provider', 'klp_mcp_server.provider.'.$config['server_provider']);
        $container->setParameter('klp_mcp_server.sse_adapter', $config['sse_adapter']);
        $container->setParameter('klp_mcp_server.adapters.redis.prefix', $config['adapters']['redis']['prefix'] ?? 'mcp_sse');
        $container->setParameter('klp_mcp_server.adapters.redis.host', $config['adapters']['redis']['host'] ?? 'default');
        $container->setParameter('klp_mcp_server.adapters.redis.ttl', $config['adapters']['redis']['ttl'] ?? 100);
        $container->setParameter('klp_mcp_server.adapters.cache.prefix', $config['adapters']['cache']['prefix'] ?? 'mcp_sse');
        $container->setParameter('klp_mcp_server.adapters.cache.ttl', $config['adapters']['cache']['ttl'] ?? 100);
        $container->setParameter('klp_mcp_server.tools', $config['tools']);

        // Set parameters for resources
        $container->setParameter('klp_mcp_server.resources', $config['resources'] ?? []);
    }
}
