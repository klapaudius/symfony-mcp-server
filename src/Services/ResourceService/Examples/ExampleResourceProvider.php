<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ResourceService\Examples;

use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceProviderInterface;

/**
 * Example ResourceProvider demonstrating dynamic resource registration.
 *
 * RECOMMENDED APPROACH: Inject resource instances via constructor.
 * This approach provides better performance and does not require resources to be public services.
 *
 * @example Basic usage with injected resource instances (RECOMMENDED):
 * ```php
 * class MyResourceProvider implements ResourceProviderInterface
 * {
 *     public function __construct(
 *         private readonly HelloWorldResource $helloResource,
 *         private readonly ProjectSummaryResource $summaryResource,
 *     ) {}
 *
 *     public function getResources(): iterable
 *     {
 *         return [$this->helloResource, $this->summaryResource];
 *     }
 * }
 * ```
 *
 * ALTERNATIVE APPROACH: Return resource class names.
 * WARNING: This requires resources to be registered as public services.
 *
 * @example Returning class names (NOT RECOMMENDED - requires public services):
 * ```php
 * public function getResources(): iterable
 * {
 *     return [
 *         HelloWorldResource::class,  // Must be public service!
 *         ProjectSummaryResource::class,
 *     ];
 * }
 *
 * // Required in config/services.yaml:
 * services:
 *     App\MCP\Resources\:
 *         resource: '../src/MCP/Resources/*'
 *         public: true  # Required for class name approach
 * ```
 *
 * Why prefer injecting resource instances?
 * - ✅ No need to make services public (better encapsulation)
 * - ✅ Better performance (resources instantiated once during compilation)
 * - ✅ Type safety with constructor injection
 * - ✅ Follows Symfony best practices
 *
 * @example Registration in services.yaml:
 * ```yaml
 * services:
 *     App\Service\MyCustomResourceProvider:
 *         autowire: true
 *         autoconfigure: true  # Automatically tags with 'klp_mcp_server.resource_provider'
 * ```
 *
 * @codeCoverageIgnore
 */
class ExampleResourceProvider implements ResourceProviderInterface
{
    /**
     * Constructor demonstrating dependency injection.
     *
     * RECOMMENDED: Inject resource instances directly for better performance
     * and to avoid requiring public service declarations.
     *
     * @example With injected resource instances:
     * ```php
     * public function __construct(
     *     private readonly HelloWorldResource $helloResource,
     *     private readonly ProjectSummaryResource $summaryResource,
     *     private readonly string $environment,
     * ) {}
     * ```
     */
    public function __construct(
        private readonly HelloWorldResource $helloResource,
        private readonly ProjectSummaryResource $summaryResource,
    ) {
        // Resources are injected and ready to use
    }

    /**
     * Returns the resources to be registered.
     *
     * RECOMMENDED: Return injected resource instances (not class names).
     *
     * This method is called at runtime when the ResourceRepository is instantiated.
     * Resources returned here will be registered with the MCP server.
     *
     * @return iterable<ResourceInterface> Array of resource instances
     */
    public function getResources(): iterable
    {
        // RECOMMENDED: Return resource instances injected in constructor
        return [
            $this->helloResource,
            $this->summaryResource,
        ];

        // NOT RECOMMENDED: Returning class names requires public services
        // return [
        //     HelloWorldResource::class,  // ⚠️ Must be public service
        //     ProjectSummaryResource::class,
        // ];

        // Example: Conditional resource loading with injected instances
        // $resources = [$this->helloResource];
        // if ($this->environment === 'dev') {
        //     $resources[] = $this->debugResource;
        // }
        // return $resources;

        // Example: Database-driven resource selection with injected instances
        // $enabledResources = $this->entityManager
        //     ->getRepository(ResourceConfiguration::class)
        //     ->findBy(['enabled' => true]);
        //
        // $resources = [];
        // foreach ($enabledResources as $config) {
        //     match ($config->getUri()) {
        //         'file:/hello-world.txt' => $resources[] = $this->helloResource,
        //         'project://summary.md' => $resources[] = $this->summaryResource,
        //         default => null,
        //     };
        // }
        // return $resources;
    }
}
