<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Routing\Loader;

use KLP\KlpMcpServer\Routing\Loader\McpRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;

class McpRouteLoaderTest extends TestCase
{
    public function testOnlySseProviderEnabledRoutes(): void
    {
        $loader = new McpRouteLoader();
        $loader->setEnabledProviders(['klp_mcp_server.provider.sse']);

        $routes = $loader->load(null);

        // Should have SSE routes
        $this->assertNotNull($routes->get('klp_mcp_server_sse'));
        $this->assertNotNull($routes->get('klp_mcp_server_sse_message'));

        // Should not have StreamableHTTP route
        $this->assertNull($routes->get('klp_mcp_server_streamable_http'));

        // Verify route paths
        $sseRoute = $routes->get('klp_mcp_server_sse');
        $this->assertEquals('/mcp/sse', $sseRoute->getPath());

        $messageRoute = $routes->get('klp_mcp_server_sse_message');
        $this->assertEquals('/mcp/messages', $messageRoute->getPath());
    }

    public function testOnlyStreamableHttpProviderEnabledRoutes(): void
    {
        $loader = new McpRouteLoader();
        $loader->setEnabledProviders(['klp_mcp_server.provider.streamable_http']);

        $routes = $loader->load(null);

        // Should have StreamableHTTP route
        $this->assertNotNull($routes->get('klp_mcp_server_streamable_http'));

        // Should not have SSE routes
        $this->assertNull($routes->get('klp_mcp_server_sse'));
        $this->assertNull($routes->get('klp_mcp_server_sse_message'));

        // Verify route path
        $streamableRoute = $routes->get('klp_mcp_server_streamable_http');
        $this->assertEquals('/mcp', $streamableRoute->getPath());
    }

    public function testBothProvidersEnabledRoutes(): void
    {
        $loader = new McpRouteLoader();
        $loader->setEnabledProviders([
            'klp_mcp_server.provider.sse',
            'klp_mcp_server.provider.streamable_http'
        ]);

        $routes = $loader->load(null);

        // Should have all routes
        $this->assertNotNull($routes->get('klp_mcp_server_sse'));
        $this->assertNotNull($routes->get('klp_mcp_server_sse_message'));
        $this->assertNotNull($routes->get('klp_mcp_server_streamable_http'));
    }

    public function testNoProvidersEnabledRoutes(): void
    {
        $loader = new McpRouteLoader();
        $loader->setEnabledProviders([]);

        $routes = $loader->load(null);

        // Should have no routes
        $this->assertCount(0, $routes);
    }

    public function testSupportsMethod(): void
    {
        $loader = new McpRouteLoader();

        $this->assertTrue($loader->supports(null, 'mcp'));
        $this->assertFalse($loader->supports(null, 'yaml'));
        $this->assertFalse($loader->supports(null, null));
    }
}
