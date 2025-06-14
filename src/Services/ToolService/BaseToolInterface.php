<?php

namespace KLP\KlpMcpServer\Services\ToolService;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;

interface BaseToolInterface
{
    /**
     * Gets the unique identifier name of the tool.
     *
     * This name is used to identify the tool in the MCP protocol and must be
     * unique within the server. It should be a descriptive, kebab-case string
     * that clearly indicates the tool's purpose.
     *
     * @return string The unique tool name (e.g., 'hello-world', 'check-version', 'stream-data').
     */
    public function getName(): string;

    /**
     * Gets the human-readable description of the tool.
     *
     * This description explains what the tool does and is shown to LLM clients
     * to help them understand when and how to use this tool.
     *
     * @return string A clear, concise description of the tool's functionality.
     */
    public function getDescription(): string;

    /**
     * Gets the JSON Schema definition for the tool's input parameters.
     *
     * The schema defines the expected structure, types, and constraints for the
     * arguments that will be passed to the execute() method. This schema is used
     * for validation and to provide type hints to LLM clients.
     *
     * @return array The JSON Schema as an associative array. Common structure:
     *               [
     *               'type' => 'object',
     *               'properties' => [
     *               'param' => ['type' => 'string', 'description' => '...'],
     *               ],
     *               'required' => ['param'],
     *               ]
     *               For tools with no parameters, use ['type' => 'object', 'properties' => new \stdClass].
     */
    public function getInputSchema(): array;

    /**
     * Gets the behavioral annotations for the tool.
     *
     * Annotations provide metadata hints about the tool's behavior, helping
     * LLM clients understand how to present and manage tools appropriately.
     *
     * @return ToolAnnotation An object containing behavioral hints:
     *                        - readOnlyHint: true if the tool doesn't modify the environment
     *                        - destructiveHint: true if the tool may perform destructive updates
     *                        - idempotentHint: true if repeated calls have no additional effect
     *                        - openWorldHint: true if the tool may interact with external entities
     */
    public function getAnnotations(): ToolAnnotation;
}
