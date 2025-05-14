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
        $container->setParameter('klp_mcp_server.middlewares', $config['middlewares']);
        $container->setParameter('klp_mcp_server.provider', 'klp_mcp_server.provider.'.$config['server_provider']);
        $container->setParameter('klp_mcp_server.adapter', 'klp_mcp_server.adapter.'.$config['sse_adapter']);
        $container->setParameter('klp_mcp_server.adapters.redis.prefix', $config['adapters']['redis']['prefix']);
        $container->setParameter('klp_mcp_server.adapters.redis.connection', $config['adapters']['redis']['connection']);
        $container->setParameter('klp_mcp_server.adapters.redis.ttl', $config['adapters']['redis']['ttl']);
        $container->setParameter('klp_mcp_server.tools', $config['tools']);
        $container->setParameter('klp_mcp_server.prompts', $config['prompts']);
        $container->setParameter('klp_mcp_server.resources', $config['resources']);
    }
}
