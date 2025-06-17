<?php

namespace KLP\KlpMcpServer\Server;

/**
 * Represents the server's capabilities according to the MCP specification.
 * This class defines what features the MCP server supports, such as tools.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
interface ServerCapabilitiesInterface
{
    /**
     * Enables the tools capability for the server instance.
     * Allows specifying optional configuration details for the tools feature.
     *
     * @param  array|null  $config  Optional configuration data specific to the tools capability.
     *                              Defaults to an empty array if not provided.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/tools
     */
    public function withTools(?array $config = []): self;

    /**
     * Enables the resources capability for the server instance.
     * Allows specifying optional configuration details for the resources feature.
     *
     * @param  array|null  $config  Optional configuration data specific to the resources capability.
     *                              Defaults to an empty array if not provided.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/resources
     */
    public function withResources(?array $config = []): self;

    /**
     * Enables the prompts capability for the server instance.
     * Allows specifying optional configuration details for the prompts feature.
     *
     * @param  array|null  $config  Optional configuration data specific to the prompts capability.
     *                              Defaults to an empty array if not provided.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/prompts
     */
    public function withPrompts(?array $config = []): self;

    /**
     * Converts the server capabilities configuration into an array format suitable for JSON serialization.
     * Only includes capabilities that are actively enabled.
     *
     * @return array<string, mixed> An associative array representing the enabled server capabilities.
     *                              For tools, if enabled but no config is set, it defaults to an empty JSON object.
     */
    public function toArray(): array;

    /**
     * Prepares and returns an array of capabilities required to initialize a message.
     *
     * @return array<string, mixed> An associative array containing capabilities such as prompts, resources, and tools. If tools are supported, additional properties may be included.
     */
    public function toInitializeMessage(): array;
}
