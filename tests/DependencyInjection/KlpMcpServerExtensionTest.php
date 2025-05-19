<?php

namespace KLP\KlpMcpServer\Tests\DependencyInjection;

use KLP\KlpMcpServer\DependencyInjection\KlpMcpServerExtension;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Small]
class KlpMcpServerExtensionTest extends TestCase
{
    private KlpMcpServerExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new KlpMcpServerExtension;
        $this->container = new ContainerBuilder;
    }

    public function test_load_sets_all_parameters_correctly(): void
    {
        $configs = [
            'enabled' => true,
            'server' => [
                'name' => 'TestServer',
                'version' => '1.0',
            ],
            'default_path' => '/default/path',
            'ping' => [
                'enabled' => true,
                'interval' => 60,
            ],
            'server_provider' => 'sse',
            'sse_adapter' => 'redis',
            'adapters' => [
                'redis' => [
                    'prefix' => 'prefix',
                    'host' => 'localhost',
                    'ttl' => 100,
                ],
                'cache' => [
                    'prefix' => 'prefix2',
                    'ttl' => 200,
                ],
            ],
            'tools' => [HelloWorldTool::class, VersionCheckTool::class],
            //            'prompts' => ['prompt1', 'prompt2'],
            //            'resources' => ['resource1', 'resource2'],
        ];

        $this->extension->load([$configs], $this->container);

        $this->assertTrue($this->container->getParameter('klp_mcp_server.enabled'));
        $this->assertEquals('TestServer', $this->container->getParameter('klp_mcp_server.server.name'));
        $this->assertEquals('1.0', $this->container->getParameter('klp_mcp_server.server.version'));
        $this->assertEquals('/default/path', $this->container->getParameter('klp_mcp_server.default_path'));
        $this->assertTrue($this->container->getParameter('klp_mcp_server.ping.enabled'));
        $this->assertEquals(60, $this->container->getParameter('klp_mcp_server.ping.interval'));
        $this->assertEquals('klp_mcp_server.provider.sse', $this->container->getParameter('klp_mcp_server.provider'));
        $this->assertEquals('redis', $this->container->getParameter('klp_mcp_server.sse_adapter'));
        $this->assertEquals('prefix', $this->container->getParameter('klp_mcp_server.adapters.redis.prefix'));
        $this->assertEquals('localhost', $this->container->getParameter('klp_mcp_server.adapters.redis.host'));
        $this->assertEquals(100, $this->container->getParameter('klp_mcp_server.adapters.redis.ttl'));
        $this->assertEquals('prefix2', $this->container->getParameter('klp_mcp_server.adapters.cache.prefix'));
        $this->assertEquals(200, $this->container->getParameter('klp_mcp_server.adapters.cache.ttl'));
        $this->assertEquals([HelloWorldTool::class, VersionCheckTool::class], $this->container->getParameter('klp_mcp_server.tools'));
        //        $this->assertEquals(['prompt1', 'prompt2'], $this->container->getParameter('klp_mcp_server.prompts'));
        //        $this->assertEquals(['resource1', 'resource2'], $this->container->getParameter('klp_mcp_server.resources'));
    }

    public function test_load_throws_exception_for_invalid_config(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $configs = [
            'enabled' => true,
            'server' => [
                'name' => null,
            ],
        ];

        $this->extension->load([$configs], $this->container);
    }
}
