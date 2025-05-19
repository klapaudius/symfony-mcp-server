<?php

namespace KLP\KlpMcpServer\Transports\SseAdapters;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SseAdapterFactory
{
    private ?\Redis $mockRedis = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Creates and retrieves an instance of SseAdapterInterface.
     *
     * @return SseAdapterInterface Returns an instance of SseAdapterInterface if the retrieved adapter is valid.
     * @throws SseAdapterException Throws an exception if the retrieved adapter is not a valid instance of SseAdapterInterface.
     */
    public function create(): SseAdapterInterface
    {
        return match ($this->container->getParameter('klp_mcp_server.sse_adapter')) {
            'redis' => $this->createRedisAdapter(),
            'cache' => $this->createCachePoolAdapter(),
            default => throw new SseAdapterException('Invalid adapter')
        };
    }

    private function createCachePoolAdapter(): CachePoolAdapter
    {
        return new CachePoolAdapter([
                'prefix' => $this->container->getParameter('klp_mcp_server.adapters.cache.prefix'),
                'ttl' => $this->container->getParameter('klp_mcp_server.adapters.cache.ttl'),
            ],
            $this->container->get('cache.app'),
            $this->logger
        );
    }

    private function createRedisAdapter(): RedisAdapter
    {
        return new RedisAdapter(
            [
                'prefix' => $this->container->getParameter('klp_mcp_server.adapters.redis.prefix'),
                'host' => $this->container->getParameter('klp_mcp_server.adapters.redis.host'),
                'ttl' => $this->container->getParameter('klp_mcp_server.adapters.redis.ttl'),
            ],
            $this->logger,
            $this->mockRedis
        );
    }
}
