<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents a text result from a tool operation.
 *
 * This class encapsulates plain text content returned by a tool.
 * It is the most common type of tool result for simple text responses.
 */
final class StructuredToolResult extends AbstractToolResult
{
    /**
     * The structured value to be returned.
     */
    private array $structuredValue;

    /**
     * Creates a new text tool result.
     *
     * @param  array  $value  The text content to be returned
     */
    public function __construct(array $structuredValue)
    {
        $this->structuredValue = $structuredValue;

        $this->setType('text');
        $this->setKey('text');
        $this->setValue(json_encode($structuredValue));
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

    /**
     * Retrieves the structured value.
     *
     * @return array The structured value.
     */
    public function getStructuredValue(): array
    {
        return $this->structuredValue;
    }
}
