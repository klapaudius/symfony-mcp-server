<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents a resource result from a tool operation.
 *
 * This class encapsulates resource data with URI, MIME type, and text content.
 * Used when a tool needs to return resource references as part of its response.
 */
final class ResourceToolResult extends AbstractToolResult
{
    /**
     * Creates a new resource tool result.
     *
     * @param  string  $uri  The URI of the resource
     * @param  string  $mimeType  The MIME type of the resource (e.g., 'text/plain', 'application/json')
     * @param  string  $value  The text content of the resource
     */
    public function __construct(private readonly string $uri, private readonly string $mimeType, string $value)
    {
        $this->setType('resource');
        $this->setKey('resource');
        $this->setValue($value);
    }

    /**
     * {@inheritDoc}
     */
    public function getSanitizedResult(): array
    {
        return [
            'type' => $this->getType(),
            $this->getKey() => [
                'uri' => $this->uri,
                'mimeType' => $this->mimeType,
                'text' => $this->getValue(),
            ],
        ];
    }
}
