<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents an abstract message in a prompt.
 * This class serves as a base for defining specific prompt messages.
 *
 * @internal
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/server/prompts
 */
abstract class AbstractPromptMessage implements PromptMessageInterface
{
    /**
     * @var string|null The role of the prompt message (e.g., 'user', 'assistant')
     */
    private ?string $role = null;

    /**
     * @var string The type of the prompt message (e.g., 'text', 'image', 'audio', 'resource')
     */
    private string $type;

    /**
     * @var string The value content of the prompt message
     */
    private string $value;

    /**
     * @var string The key identifier for the result data in the response
     */
    private string $key;

    /**
     * Gets the role of the prompt message.
     *
     * @return string|null The message role
     */
    protected function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * Sets the role of the prompt message.
     *
     * @param  string  $role  The message role to set
     */
    protected function setRole(string $role): void
    {
        $this->role = $role;
    }

    /**
     * Gets the type of the prompt message.
     *
     * @return string The message type
     */
    protected function getType(): string
    {
        return $this->type;
    }

    /**
     * Sets the type of the prompt message.
     *
     * @param  string  $type  The message type to set
     */
    protected function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Gets the key identifier for the message data.
     *
     * @return string The message key
     */
    protected function getKey(): string
    {
        return $this->key;
    }

    /**
     * Sets the key identifier for the message data.
     *
     * @param  string  $key  The message key to set
     */
    protected function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * Gets the value content of the prompt message.
     *
     * @return string The message value
     */
    protected function getValue(): string
    {
        return $this->value;
    }

    /**
     * Sets the value content of the prompt message.
     *
     * @param  string  $value  The message value to set
     */
    protected function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Get the prompt messages with resolved arguments.
     * This method receives the actual argument values and should return
     * the formatted prompt message.
     *
     * @return array<int, array{role: string, content: array{type: string, text?: string, resource?: array{uri: string}}}>
     */
    abstract public function getSanitizedMessage(): array;
}
