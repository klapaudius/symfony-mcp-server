<?php

namespace KLP\KlpMcpServer\Server;

use stdClass;

/**
 * Represents the server's capabilities according to the MCP specification.
 * This class defines what features the MCP server supports, such as tools.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
final class ServerCapabilities implements ServerCapabilitiesInterface
{
    /**
     * Indicates whether the server supports the MCP tools feature.
     * If true, the server can register and expose tools to the client.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/tools
     */
    private bool $supportsTools = false;

    /**
     * Indicates whether the server supports the MCP resources feature.
     * If true, the server can register and expose resources to the client.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/resources
     */
    private bool $supportsResources = false;

    /**
     * Optional configuration specific to the tools capability.
     * This structure can be defined by the specific server implementation
     * to provide further details about the supported tools, if needed.
     * If null and tools are supported, it might default to an empty object during serialization.
     */
    private ?array $toolsConfig = null;

    /**
     * Optional configuration specific to the resources capability.
     * This structure can be defined by the specific server implementation
     */
    private ?array $resourcesConfig = null;

    /**
     * Enables the tools capability for the server instance.
     * Allows specifying optional configuration details for the tools feature.
     *
     * @param  array|null  $config  Optional configuration data specific to the tools capability.
     *                              Defaults to an empty array if not provided.
     * @return self Returns the instance for method chaining.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/tools
     */
    public function withTools(?array $config = []): self
    {
        $this->supportsTools = true;
        $this->toolsConfig = $config;

        return $this;
    }

    /**
     * Enables the tools capability for the server instance.
     * Allows specifying optional configuration details for the tools feature.
     *
     * @param  array|null  $config  Optional configuration data specific to the tools capability.
     *                              Defaults to an empty array if not provided.
     * @return self Returns the instance for method chaining.
     *
     * @see https://modelcontextprotocol.io/docs/concepts/tools
     */
    public function withResources(?array $config = []): self
    {
        $this->supportsResources = true;
        $this->resourcesConfig = $config;

        return $this;
    }

    /**
     * Converts the server capabilities configuration into an array format suitable for JSON serialization.
     * Only includes capabilities that are actively enabled.
     *
     * @return array<string, mixed> An associative array representing the enabled server capabilities.
     *                              For tools, if enabled but no config is set, it defaults to an empty JSON object.
     */
    public function toArray(): array
    {
        $capabilities = [
            'prompts' => new stdClass,
            'resources' => $this->supportsResources ? $this->resourcesConfig : new stdClass,
        ];

        if ($this->supportsTools) {
            // Use an empty stdClass to ensure JSON serialization as {} instead of [] for empty arrays.
            $capabilities['tools'] = $this->toolsConfig ?: new stdClass;
        }

        return $capabilities;
    }

    /**
     * Prepares and returns an array of capabilities required to initialize a message.
     *
     * @return array An associative array containing capabilities such as prompts, resources, and tools. If tools are supported, additional properties may be included.
     */
    public function toInitializeMessage(): array
    {
        $capabilities = [
            'prompts' => new stdClass,
            'resources' => new stdClass,
            'tools' => new stdClass,
        ];
        if ($this->supportsTools) {
            $capabilities['tools']->listChanged = true;
        }
        if ($this->supportsResources) {
            $capabilities['resources']->listChanged = true;
        }

        return $capabilities;
    }
}
