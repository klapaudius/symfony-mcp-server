<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents an abstract result of a tool's operation.
 * This class serves as a base for defining specific tool results.
 *
 * @internal
 * @see https://modelcontextprotocol.io/specification/2025-03-26/server/tools
 */
abstract class AbstractToolResult implements ToolResultInterface
{
    /**
     * @var string The type of the tool result (e.g., 'text', 'image', 'audio', 'resource')
     */
    private string $type;

    /**
     * @var string The value content of the tool result
     */
    private string $value;

    /**
     * @var string The key identifier for the result data in the response
     */
    private string $key;

    /**
     * Gets the type of the tool result.
     *
     * @return string The result type
     */
    protected function getType(): string
    {
        return $this->type;
    }

    /**
     * Sets the type of the tool result.
     *
     * @param string $type The result type to set
     */
    protected function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Gets the key identifier for the result data.
     *
     * @return string The result key
     */
    protected function getKey(): string
    {
        return $this->key;
    }

    /**
     * Sets the key identifier for the result data.
     *
     * @param string $key The result key to set
     */
    protected function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * Gets the value content of the tool result.
     *
     * @return string The result value
     */
    protected function getValue(): string
    {
        return $this->value;
    }

    /**
     * Sets the value content of the tool result.
     *
     * @param string $value The result value to set
     */
    protected function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Returns the sanitized result array formatted according to MCP specification.
     *
     * @return array<string, mixed> The sanitized result data
     */
    abstract public function getSanitizedResult(): array;
}
