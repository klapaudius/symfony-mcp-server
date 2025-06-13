<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents a text result from a tool operation.
 *
 * This class encapsulates plain text content returned by a tool.
 * It is the most common type of tool result for simple text responses.
 */
final class TextToolResult extends AbstractToolResult
{
    /**
     * Creates a new text tool result.
     *
     * @param string $value The text content to be returned
     */
    public function __construct(string $value)
    {
        $this->setType('text');
        $this->setKey('text');
        $this->setValue($value);
    }

    /**
     * {@inheritDoc}
     */
    public function getSanitizedResult(): array
    {
        return [
            'type' => $this->getType(),
            $this->getKey() => $this->getValue(),
        ];
    }
}
