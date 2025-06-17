<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

use KLP\KlpMcpServer\Services\PromptService\Message\AbstractPromptMessage;
use KLP\KlpMcpServer\Services\PromptService\Message\PromptMessageInterface;

/**
 * Represents a text result from a tool operation.
 *
 * This class encapsulates plain text content returned by a tool.
 * It is the most common type of tool result for simple text responses.
 */
final class CollectionPromptMessage
{
    private array $messages = [];

    public function addMessage(PromptMessageInterface $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    final public function getSanitizedMessages(array $arguments = []): array
    {
        $result = [];
        foreach ($this->messages as $message) {
            $result[] = $message->getSanitizedMessage($arguments);
        }

        return $result;
    }
}
