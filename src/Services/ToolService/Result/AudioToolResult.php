<?php

namespace KLP\KlpMcpServer\Services\ToolService\Result;

/**
 * Represents an audio result from a tool operation.
 *
 * This class encapsulates audio data encoded in base64 format along with its MIME type.
 * Used when a tool needs to return audio content as part of its response.
 */
final class AudioToolResult extends AbstractToolResult
{
    /**
     * Creates a new audio tool result.
     *
     * @param  string  $base64EncodedData  The audio data encoded in base64 format
     * @param  string  $mimeType  The MIME type of the audio (e.g., 'audio/mpeg', 'audio/wav')
     */
    public function __construct(string $base64EncodedData, private readonly string $mimeType)
    {
        $this->setType('audio');
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
