<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents an abstract result of a tool's operation.
 * This class serves as a base for defining specific tool results.
 */
interface ToolResultInterface
{
    /**
     * Returns the sanitized result array formatted according to MCP specification.
     *
     * This method must return a properly formatted array that conforms to the
     * Model Context Protocol specification for tool results.
     *
     * @return array<string, mixed> The sanitized result data
     */
    public function getSanitizedResult(): array;
}
