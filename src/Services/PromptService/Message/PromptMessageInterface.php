<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents an abstract message in a prompt.
 * This class serves as a base for defining specific prompt messages.
 */
interface PromptMessageInterface
{
    const ROLE_USER = 'user';

    const ROLE_ASSISTANT = 'assistant';

    /**
     * Returns the sanitized messages array formatted according to MCP specification.
     *
     * This method must return a properly formatted array that conforms to the
     * Model Context Protocol specification for prompt messages.
     *
     * @return array The sanitized messages data
     */
    public function getSanitizedMessage(): array;
}
