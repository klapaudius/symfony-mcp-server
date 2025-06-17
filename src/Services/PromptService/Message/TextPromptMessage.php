<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents a text message in a prompt.
 *
 * This class encapsulates plain text content returned by a prompt.
 * It is the most common type of prompt message for simple text messages.
 */
final class TextPromptMessage extends AbstractPromptMessage
{
    /**
     * Creates a new text prompt message.
     *
     * @param string $value The text content to be returned
     */
    public function __construct(string $role, string $value)
    {
        $this->setRole($role);
        $this->setType('text');
        $this->setKey('text');
        $this->setValue($value);
    }

    /**
     * {@inheritDoc}
     */
    public function getSanitizedMessage(array $arguments = []): array
    {
        return [
            'role' => $this->getRole(),
            'content' => [
                'type' => $this->getType(),
                $this->getKey() => $this->getValue($arguments),
            ]
        ];
    }
}
