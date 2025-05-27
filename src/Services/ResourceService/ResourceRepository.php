<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Manages the registration and retrieval of resources available to the MCP server.
 * Resources must implement the ResourceInterface.
 *
 * @see [https://modelcontextprotocol.io/docs/concepts/resources](https://modelcontextprotocol.io/docs/concepts/resources)
 */
class ResourceRepository
{
    /**
     * Holds the registered resource instances, keyed by their name.
     *
     * @var array<string, ResourceInterface>
     */
    protected array $resources = [];

    /**
     * Holds the registered resource providers, keyed by their name.
     *
     * @var array<string, ResourceProviderInterface>
     */
    protected array $providers = [];

    /**
     * The Symfony container.
     */
    protected ContainerInterface $container;

    /**
     * Constructor.
     *
     * @param  ContainerInterface  $container  The Symfony service container. If null, it resolves from the facade.
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        if ($resources = $container->getParameter('klp_mcp_server.resources')) {
            $this->registerMany($resources);
        }
    }

    /**
     * Registers multiple resources at once.
     *
     * @param  array<string|ResourceInterface>  $resources  An array of resource class strings or ResourceInterface instances.
     * @return $this The current ResourceRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a resource does not implement ResourceInterface.
     */
    public function registerMany(array $resources): self
    {
        foreach ($resources as $resource) {
            $this->register($resource);
        }

        return $this;
    }

    /**
     * Registers a single resource.
     * If a class string is provided, it resolves the resource from the container.
     *
     * @param  string|ResourceInterface  $resource  The resource class string or a ResourceInterface instance.
     * @return $this The current ResourceRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If the provided $resource is not a string or ResourceInterface, or if the resolved object does not implement ResourceInterface.
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function register(string|ResourceInterface $resource): self
    {
        if (is_string($resource)) {
            $resource = $this->container->get($resource);
        }

        if (! $resource instanceof ResourceInterface) {
            throw new InvalidArgumentException('Resource must implement the '.ResourceInterface::class);
        }

        $this->resources[$resource->getUri()] = $resource;

        return $this;
    }

    /**
     * Retrieves all registered resources.
     *
     * @return array<string, ResourceInterface> An array of registered resource instances, keyed by their URI.
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Retrieves a specific resource by its URI.
     *
     * @param  string  $uri  The URI of the resource to retrieve.
     * @return ResourceInterface|null The resource instance if found, otherwise null.
     */
    public function getResource(string $uri): ?ResourceInterface
    {
        // Check if the resource is already loaded
        if (isset($this->resources[$uri])) {
            return $this->resources[$uri];
        }

        // Try to load the resource from a provider
        foreach ($this->providers as $provider) {
            $resource = $provider->getResource($uri);
            if ($resource) {
                $this->resources[$uri] = $resource;
                return $resource;
            }
        }

        return null;
    }

    /**
     * Generates an array of schemas for all registered resources, suitable for the MCP capabilities response.
     * Includes name, description, inputSchema, and optional annotations for each resource.
     *
     * @return array<int, array{uri: string, name: string, description: string, mimeType: string}> An array of resource schemas.
     */
    public function getResourceSchemas(): array
    {
        $schemas = [];

        foreach ($this->resources as $resource) {
            $schemas[] = [
                'uri' => $resource->getUri(),
                'name' => $resource->getName(),
                'description' => $resource->getDescription(),
                'mimeType' => $resource->getMimeType()
            ];
        }

        return $schemas;
    }
}
