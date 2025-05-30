<?php

namespace KLP\KlpMcpServer\Services\ResourceService;

/**
 * Interface for resource template.
 * Resource templates are responsible for managing dynamic resources.
 */
interface ResourceTemplateInterface extends ResourceDescriptorInterface
{
    /**
     * Get the URI template
     *
     * @return string The URI Template.
     */
    public function getUriTemplate(): string;

    /**
     * Get a resource by its URI.
     *
     * @param string $uri The URI of the resource to retrieve.
     * @return ResourceInterface|null The resource if found, null otherwise.
     */
    public function getResource(string $uri): ?ResourceInterface;

    /**
     * Check if a resource exists.
     *
     * @param string $uri The URI of the resource to check.
     * @return bool True if the resource exists, false otherwise.
     */
    public function resourceExists(string $uri): bool;
}
