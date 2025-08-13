<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents a text result from a tool operation.
 *
 * This class encapsulates plain text content returned by a tool.
 * It is the most common type of tool result for simple text responses.
 */
final class CollectionPromptMessage
{
    public function __construct(private array $messages = []) {}

    public function addMessage(PromptMessageInterface $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    final public function getSanitizedMessages(): array
    {
        $result = [];
        foreach ($this->messages as $message) {
            $result[] = $message->getSanitizedMessage();
        }

        return $result;
    }
}
