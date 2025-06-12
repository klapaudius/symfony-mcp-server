<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents an image result from a tool operation.
 *
 * This class encapsulates image data encoded in base64 format along with its MIME type.
 * Used when a tool needs to return image content as part of its response.
 */
final class ImageToolResult extends AbstractToolResult
{
    /**
     * Creates a new image tool result.
     *
     * @param string $base64EncodedData The image data encoded in base64 format
     * @param string $mimeType The MIME type of the image (e.g., 'image/jpeg', 'image/png')
     */
    public function __construct(string $base64EncodedData, private readonly string $mimeType)
    {
        $this->setType('image');
        $this->setKey('data');
        $this->setValue($base64EncodedData);
    }

    /**
     * {@inheritDoc}
     */
    public function getSanitizedResult(): array
    {
        return [
            'type' => $this->getType(),
            $this->getKey() => $this->getValue(),
            'mimeType' => $this->mimeType,
        ];
    }
}
