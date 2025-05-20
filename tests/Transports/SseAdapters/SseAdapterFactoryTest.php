<?php

namespace KLP\KlpMcpServer\Tests\Transports\SseAdapters;

use KLP\KlpMcpServer\Transports\SseAdapters\CachePoolAdapter;
use KLP\KlpMcpServer\Transports\SseAdapters\RedisAdapter;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterFactory;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Redis;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Small]
class SseAdapterFactoryTest extends TestCase
{
    private Redis|MockObject $redisMock;

    protected function setUp(): void
    {
        if (! class_exists(Redis::class)) {
            eval(<<<'PHPUNIT_EVAL'
                class Redis {
                    const OPT_PREFIX = 2;
                    public function __call($name, $arguments) {}
                    public function connect($host, $port) {}
                    public function setOption($option, $value) {}
                    public function rpush($key, $value) {}
                    public function expire($key, $ttl) {}
                    public function lpop($key) {}
                    public function llen($key) {}
                    public function del($key) {}
                    public function set($key, $value) {}
                    public function get($key) {}
                    public function pexpire($key, $ttl) {}
                    public function pexpireat($key, $timestamp) {}
                    public function pttl($key) {}
                    public function psetex($key, $ttl, $value) {}
                }
            PHPUNIT_EVAL);
        }
        $this->redisMock = $this->createMock(Redis::class);
    }

    public function test_create_returns_redis_adapter_when_config_is_redis(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $invocations = [
            'klp_mcp_server.sse_adapter',
            'klp_mcp_server.adapters.redis.prefix',
            'klp_mcp_server.adapters.redis.host',
            'klp_mcp_server.adapters.redis.ttl',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls('redis', 'prefix', 'localhost', 3600);

        $factory = new SseAdapterFactory($container, $logger);

        $reflection = new \ReflectionClass($factory);
        $property = $reflection->getProperty('mockRedis');
        $property->setValue($factory, $this->redisMock);

        $adapter = $factory->create();

        $this->assertInstanceOf(RedisAdapter::class, $adapter);
    }

    public function test_create_returns_cache_pool_adapter_when_config_is_cache(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $cachePool = $this->createMock(CacheItemPoolInterface::class);

        $invocations = [
            'klp_mcp_server.sse_adapter',
            'klp_mcp_server.adapters.cache.prefix',
            'klp_mcp_server.adapters.cache.ttl',
        ];
        $container->expects($matcher = $this->exactly(count($invocations)))
            ->method('getParameter')
            ->with($this->callback(function ($argument) use ($invocations, $matcher) {
                $this->assertEquals($argument, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }))
            ->willReturnOnConsecutiveCalls('cache', 'prefix', 600);

        $container->expects($this->once())
            ->method('get')
            ->with('cache.app')
            ->willReturn($cachePool);

        $factory = new SseAdapterFactory($container, $logger);
        $adapter = $factory->create();

        $this->assertInstanceOf(CachePoolAdapter::class, $adapter);
    }

    public function test_create_throws_exception_for_invalid_adapter(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('getParameter')
            ->with('klp_mcp_server.sse_adapter')
            ->willReturn('invalid_adapter');

        $factory = new SseAdapterFactory($container);

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Invalid adapter');

        $factory->create();
    }
}
