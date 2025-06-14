<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Routing\Loader;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Custom route loader that conditionally loads routes based on enabled server providers
 */
class McpRouteLoader implements LoaderInterface
{
    private array $enabledProviders = [];

    private string $defaultPath = 'mcp';

    private bool $loaded = false;

    public function setEnabledProviders(array $providers): void
    {
        $this->enabledProviders = $providers;
    }

    public function setDefaultPath(string $defaultPath): void
    {
        $this->defaultPath = $defaultPath;
    }

    public function load($resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('MCP routes already loaded');
        }

        $routes = new RouteCollection;

        // StreamableHTTP routes
        if ($this->isProviderEnabled('streamable_http')) {
            $routes->add('klp_mcp_server_streamable_http', new Route(
                '/'.$this->defaultPath,
                ['_controller' => 'klp_mcp_server.controller.streamable_http::handle']
            ));
        }

        // SSE routes
        if ($this->isProviderEnabled('sse')) {
            $routes->add('klp_mcp_server_sse', new Route(
                '/'.$this->defaultPath.'/sse',
                ['_controller' => 'klp_mcp_server.controller.sse::handle']
            ));

            $routes->add('klp_mcp_server_sse_message', new Route(
                '/'.$this->defaultPath.'/messages',
                ['_controller' => 'klp_mcp_server.controller.message::handle'],
                [],
                [],
                '',
                [],
                ['POST']
            ));
        }

        $this->loaded = true;

        return $routes;
    }

    public function supports($resource, ?string $type = null): bool
    {
        return $type === 'mcp';
    }

    public function getResolver(): LoaderResolverInterface
    {
        // Return a dummy resolver as it's required by the interface
        return new class implements LoaderResolverInterface
        {
            public function resolve($resource, ?string $type = null): LoaderInterface|false
            {
                return false;
            }

            public function addLoader(LoaderInterface $loader): void
            {
                // Not needed
            }

            public function getLoaders(): array
            {
                return [];
            }
        };
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        // Not needed for this implementation
    }

    private function isProviderEnabled(string $provider): bool
    {
        return in_array('klp_mcp_server.provider.'.$provider, $this->enabledProviders, true);
    }
}
