<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents a text result from a tool operation.
 *
 * This class encapsulates plain text content returned by a tool.
 * It is the most common type of tool result for simple text responses.
 */
final class CollectionToolResult extends AbstractToolResult
{
    private array $items = [];

    public function addItem(ToolResultInterface $item): void
    {
        if ($item instanceof CollectionToolResult) {
            throw new \InvalidArgumentException('CollectionToolResult cannot contain other CollectionToolResult.');
        }
        $this->items[] = $item;
    }

    /**
     * {@inheritDoc}
     */
    public function getSanitizedResult(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = $item->getSanitizedResult();
        }

        return $result;
    }
}
