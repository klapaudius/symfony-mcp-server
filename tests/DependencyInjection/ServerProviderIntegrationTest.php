<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\DependencyInjection;

use KLP\KlpMcpServer\DependencyInjection\CompilerPass\ConditionalRoutePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ServerProviderIntegrationTest extends TestCase
{
    public function test_compiler_pass_sets_enabled_providers(): void
    {
        $container = new ContainerBuilder;

        // Set up the providers parameter
        $container->setParameter('klp_mcp_server.providers', [
            'klp_mcp_server.provider.sse',
        ]);
        $container->setParameter('klp_mcp_server.default_path', 'mcp');

        // Register a mock route loader service
        $routeLoader = $container->register('klp_mcp_server.route_loader', \stdClass::class);

        // Apply the compiler pass
        $pass = new ConditionalRoutePass;
        $pass->process($container);

        // Verify the method calls were added
        $methodCalls = $routeLoader->getMethodCalls();
        $this->assertCount(2, $methodCalls);
        $this->assertEquals('setEnabledProviders', $methodCalls[0][0]);
        $this->assertEquals([['klp_mcp_server.provider.sse']], $methodCalls[0][1]);
        $this->assertEquals('setDefaultPath', $methodCalls[1][0]);
        $this->assertEquals(['mcp'], $methodCalls[1][1]);
    }

    public function test_compiler_pass_with_multiple_providers(): void
    {
        $container = new ContainerBuilder;

        // Set up multiple providers
        $container->setParameter('klp_mcp_server.providers', [
            'klp_mcp_server.provider.sse',
            'klp_mcp_server.provider.streamable_http',
        ]);
        $container->setParameter('klp_mcp_server.default_path', 'mcp');

        // Register a mock route loader service
        $routeLoader = $container->register('klp_mcp_server.route_loader', \stdClass::class);

        // Apply the compiler pass
        $pass = new ConditionalRoutePass;
        $pass->process($container);

        // Verify the method calls were added
        $methodCalls = $routeLoader->getMethodCalls();
        $this->assertCount(2, $methodCalls);
        $this->assertEquals('setEnabledProviders', $methodCalls[0][0]);
        $this->assertEquals([[
            'klp_mcp_server.provider.sse',
            'klp_mcp_server.provider.streamable_http',
        ]], $methodCalls[0][1]);
        $this->assertEquals('setDefaultPath', $methodCalls[1][0]);
        $this->assertEquals(['mcp'], $methodCalls[1][1]);
    }

    public function test_compiler_pass_without_provider_parameter(): void
    {
        $container = new ContainerBuilder;

        // Don't set the providers parameter

        // Register a mock route loader service
        $routeLoader = $container->register('klp_mcp_server.route_loader', \stdClass::class);

        // Apply the compiler pass
        $pass = new ConditionalRoutePass;
        $pass->process($container);

        // Verify no method call was added
        $methodCalls = $routeLoader->getMethodCalls();
        $this->assertCount(0, $methodCalls);
    }
}
