<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\ResourceService;

/**
 * Interface for dynamic resource providers.
 *
 * Implement this interface to dynamically provide resources to the MCP server
 * based on custom logic (e.g., database queries, environment configuration,
 * feature flags, or any other runtime conditions).
 *
 * All classes implementing this interface will be automatically discovered
 * and registered by the ResourcesDefinitionCompilerPass during container compilation.
 *
 * @example
 * ```php
 * class DatabaseResourceProvider implements ResourceProviderInterface
 * {
 *     public function __construct(
 *         private EntityManagerInterface $entityManager
 *     ) {}
 *
 *     public function getResources(): iterable
 *     {
 *         $resources = $this->entityManager
 *             ->getRepository(CustomResource::class)
 *             ->findBy(['enabled' => true]);
 *
 *         return array_map(
 *             fn($resource) => $resource->getResourceClass(),
 *             $resources
 *         );
 *     }
 * }
 * ```
 */
interface ResourceProviderInterface
{
    /**
     * Returns an iterable collection of resources to register.
     *
     * Each item can be either:
     * - A fully-qualified class name (string) that implements ResourceInterface
     * - An instance of ResourceInterface
     *
     * The returned resources will be registered with the ResourceRepository alongside
     * resources defined in the YAML configuration.
     *
     * @return iterable<string|ResourceInterface> Array of resource class names or instances
     */
    public function getResources(): iterable;
}
