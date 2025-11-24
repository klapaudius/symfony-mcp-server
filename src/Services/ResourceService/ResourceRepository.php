<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
     * Holds the registered resources templates instances, keyed by their name.
     *
     * @var array<string, ResourceTemplateInterface>
     */
    protected array $resourceTemplates = [];

    /**
     * The Symfony container.
     */
    protected ContainerInterface $container;

    /**
     * The logger instance.
     */
    protected ?LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param  ContainerInterface  $container  The Symfony service container. If null, it resolves from the facade.
     * @param  LoggerInterface|null  $logger  Optional logger instance for debugging resource registration.
     */
    public function __construct(ContainerInterface $container, ?LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->logger?->debug('ResourceRepository initialized');

        if ($resources = $container->getParameter('klp_mcp_server.resources')) {
            $this->logger?->debug('Registering resources from configuration', ['resources' => $resources]);
            $this->registerMany($resources);
        }
        if ($resourceTemplateConfigs = $container->getParameter('klp_mcp_server.resources_templates')) {
            $this->logger?->debug('Registering resource templates from configuration', ['resource_templates' => $resourceTemplateConfigs]);
            $this->registerManyResourceTemplates($resourceTemplateConfigs);
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
     * Registers resources from a ResourceProviderInterface.
     *
     * This method is called by the ResourcesDefinitionCompilerPass for each
     * discovered resource provider. It retrieves resources from the provider and
     * registers them with the repository.
     *
     * @param  ResourceProviderInterface  $provider  The resource provider instance.
     * @return $this The current ResourceRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a resource does not implement ResourceInterface.
     */
    public function registerProvider(ResourceProviderInterface $provider): self
    {
        $providerClass = get_class($provider);

        $this->logger?->debug('Registering resources from ResourceProvider', [
            'provider' => $providerClass,
        ]);

        $resources = $provider->getResources();
        $resourceCount = is_countable($resources) ? count($resources) : 0;

        foreach ($resources as $resource) {
            $this->register($resource);
        }

        $this->logger?->debug('Successfully registered resources from ResourceProvider', [
            'provider' => $providerClass,
            'resource_count' => $resourceCount,
        ]);

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
     * Registers multiple resource providers at once.
     *
     * @param  array<string|ResourceTemplateInterface>  $providers  An array of provider class strings or ResourceProviderInterface instances.
     * @return $this The current ResourceRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If a provider does not implement ResourceProviderInterface.
     */
    public function registerManyResourceTemplates(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->registerResourceTemplate($provider);
        }

        return $this;
    }

    /**
     * Registers a single resource provider.
     * If a class string is provided, it resolves the provider from the container.
     *
     * @param  string|ResourceTemplateInterface  $resourceTemplate  The resource template class string or a ResourceTemplateInterface instance.
     * @return $this The current ResourceRepository instance for method chaining.
     *
     * @throws InvalidArgumentException If the provided $provider is not a string or ResourceProviderInterface, or if the resolved object does not implement ResourceProviderInterface.
     * @throws ServiceNotFoundException
     */
    public function registerResourceTemplate(string|ResourceTemplateInterface $resourceTemplate): self
    {
        if (is_string($resourceTemplate)) {
            $resourceTemplate = $this->container->get($resourceTemplate);
        }

        if (! $resourceTemplate instanceof ResourceTemplateInterface) {
            throw new InvalidArgumentException('ResourceTemplate must implement the '.ResourceTemplateInterface::class);
        }

        $this->resourceTemplates[$resourceTemplate->getUriTemplate()] = $resourceTemplate;

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

        // Try to load the resource from a resource template
        foreach ($this->resourceTemplates as $resourceTemplate) {
            $resource = $resourceTemplate->getResource($uri);
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
                'mimeType' => $resource->getMimeType(),
            ];
        }

        return $schemas;
    }

    /**
     * Generates an array of schemas for all registered resource templates, suitable for the MCP capabilities response.
     *
     * @return array<int, array{uriTemplate: string, name: string, description: string, mimeType: string}> An array of resource template schemas.
     */
    public function getResourceTemplateSchemas(): array
    {
        $schemas = [];

        foreach ($this->resourceTemplates as $resourceTemplate) {
            $schemas[] = [
                'uriTemplate' => $resourceTemplate->getUriTemplate(),
                'name' => $resourceTemplate->getName(),
                'description' => $resourceTemplate->getDescription(),
                'mimeType' => $resourceTemplate->getMimeType(),
            ];
        }

        return $schemas;
    }
}
