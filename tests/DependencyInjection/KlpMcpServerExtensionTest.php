<?php

namespace KLP\KlpMcpServer\Tests\DependencyInjection;

use KLP\KlpMcpServer\DependencyInjection\KlpMcpServerExtension;
use KLP\KlpMcpServer\Services\PromptService\Examples\HelloWorldPrompt;
use KLP\KlpMcpServer\Services\ResourceService\Examples\HelloWorldResource;
use KLP\KlpMcpServer\Services\ResourceService\Examples\McpDocumentationResource;
use KLP\KlpMcpServer\Services\ToolService\Examples\HelloWorldTool;
use KLP\KlpMcpServer\Services\ToolService\Examples\VersionCheckTool;
use PHPUnit\Framework\Attributes\Group;
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

    /**
     * Tests that all parameters are correctly set when loading a valid configuration
     */
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
            'server_providers' => ['streamable_http', 'sse'],
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
            'resources' => [HelloWorldResource::class],
            'resources_templates' => [McpDocumentationResource::class],
            'prompts' => [],
        ];

        $this->extension->load([$configs], $this->container);

        $this->assertTrue($this->container->getParameter('klp_mcp_server.enabled'));
        $this->assertEquals('TestServer', $this->container->getParameter('klp_mcp_server.server.name'));
        $this->assertEquals('1.0', $this->container->getParameter('klp_mcp_server.server.version'));
        $this->assertEquals('/default/path', $this->container->getParameter('klp_mcp_server.default_path'));
        $this->assertTrue($this->container->getParameter('klp_mcp_server.ping.enabled'));
        $this->assertEquals(60, $this->container->getParameter('klp_mcp_server.ping.interval'));
        $this->assertEquals(['klp_mcp_server.provider.streamable_http', 'klp_mcp_server.provider.sse'], $this->container->getParameter('klp_mcp_server.providers'));
        $this->assertEquals('redis', $this->container->getParameter('klp_mcp_server.sse_adapter'));
        $this->assertEquals('prefix', $this->container->getParameter('klp_mcp_server.adapters.redis.prefix'));
        $this->assertEquals('localhost', $this->container->getParameter('klp_mcp_server.adapters.redis.host'));
        $this->assertEquals(100, $this->container->getParameter('klp_mcp_server.adapters.redis.ttl'));
        $this->assertEquals('prefix2', $this->container->getParameter('klp_mcp_server.adapters.cache.prefix'));
        $this->assertEquals(200, $this->container->getParameter('klp_mcp_server.adapters.cache.ttl'));
        $this->assertEquals([HelloWorldTool::class, VersionCheckTool::class], $this->container->getParameter('klp_mcp_server.tools'));
        $this->assertEquals([HelloWorldResource::class], $this->container->getParameter('klp_mcp_server.resources'));
        $this->assertEquals([McpDocumentationResource::class], $this->container->getParameter('klp_mcp_server.resources_templates'));
        $this->assertEquals([], $this->container->getParameter('klp_mcp_server.prompts'));
    }

    /**
     * Tests that all parameters are correctly set when loading a valid configuration with deprecated server_provider
     */
    #[Group('legacy')]
    public function test_load_sets_all_parameters_correctly_with_deprecated_server_provider_key(): void
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
            'prompts' => [HelloWorldPrompt::class],
            'resources' => [HelloWorldResource::class],
            'resources_templates' => [McpDocumentationResource::class],
        ];

        $this->extension->load([$configs], $this->container);

        $this->assertTrue($this->container->getParameter('klp_mcp_server.enabled'));
        $this->assertEquals('TestServer', $this->container->getParameter('klp_mcp_server.server.name'));
        $this->assertEquals('1.0', $this->container->getParameter('klp_mcp_server.server.version'));
        $this->assertEquals('/default/path', $this->container->getParameter('klp_mcp_server.default_path'));
        $this->assertTrue($this->container->getParameter('klp_mcp_server.ping.enabled'));
        $this->assertEquals(60, $this->container->getParameter('klp_mcp_server.ping.interval'));
        $this->assertEquals(['klp_mcp_server.provider.sse'], $this->container->getParameter('klp_mcp_server.providers'));
        $this->assertEquals('redis', $this->container->getParameter('klp_mcp_server.sse_adapter'));
        $this->assertEquals('prefix', $this->container->getParameter('klp_mcp_server.adapters.redis.prefix'));
        $this->assertEquals('localhost', $this->container->getParameter('klp_mcp_server.adapters.redis.host'));
        $this->assertEquals(100, $this->container->getParameter('klp_mcp_server.adapters.redis.ttl'));
        $this->assertEquals('prefix2', $this->container->getParameter('klp_mcp_server.adapters.cache.prefix'));
        $this->assertEquals(200, $this->container->getParameter('klp_mcp_server.adapters.cache.ttl'));
        $this->assertEquals([HelloWorldTool::class, VersionCheckTool::class], $this->container->getParameter('klp_mcp_server.tools'));
        $this->assertEquals([HelloWorldResource::class], $this->container->getParameter('klp_mcp_server.resources'));
        $this->assertEquals([HelloWorldPrompt::class], $this->container->getParameter('klp_mcp_server.prompts'));
        $this->assertEquals([McpDocumentationResource::class], $this->container->getParameter('klp_mcp_server.resources_templates'));
    }

    /**
     * Tests that prompts parameter is correctly set
     */
    public function test_load_sets_prompts_parameter(): void
    {
        $configs = [
            'prompts' => [
                HelloWorldPrompt::class,
            ],
        ];

        $this->extension->load([$configs], $this->container);

        $this->assertEquals(
            [HelloWorldPrompt::class],
            $this->container->getParameter('klp_mcp_server.prompts')
        );
    }

    /**
     * Tests that an exception is thrown when an invalid configuration is provided
     */
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
