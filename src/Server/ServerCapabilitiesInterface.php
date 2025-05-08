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
     * @param array|null $config Optional configuration data specific to the tools capability.
     *                              Defaults to an empty array if not provided.
     * @return \KLP\KlpMcpServer\Server\ServerCapabilities
     *
     * @see https://modelcontextprotocol.io/docs/concepts/tools
     */
    public function withTools(?array $config = []): \KLP\KlpMcpServer\Server\ServerCapabilities;

    /**
     * Converts the server capabilities configuration into an array format suitable for JSON serialization.
     * Only includes capabilities that are actively enabled.
     *
     * @return array<string, mixed> An associative array representing the enabled server capabilities.
     *                              For tools, if enabled but no config is set, it defaults to an empty JSON object.
     */
    public function toArray(): array;
}
