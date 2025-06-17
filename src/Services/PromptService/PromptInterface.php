<?php

namespace KLP\KlpMcpServer\Services\PromptService;

use KLP\KlpMcpServer\Services\PromptService\Message\CollectionPromptMessage;

/**
 * Interface for implementing MCP prompts.
 * Prompts are predefined templates that guide LLM interactions.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/prompts
 */
interface PromptInterface
{
    /**
     * Get the unique name of the prompt.
     */
    public function getName(): string;

    /**
     * Get the human-readable description of what this prompt does.
     */
    public function getDescription(): string;

    /**
     * Get the list of arguments this prompt accepts.
     * Each argument should define name, description, and whether it's required.
     *
     * @return array<int, array{name: string, description?: string, required?: bool}>
     */
    public function getArguments(): array;

    /**
     * Retrieve the collection of messages associated with the prompt.
     *
     * @return CollectionPromptMessage The collection of prompt messages.
     */
    public function getMessages(): CollectionPromptMessage;
}
