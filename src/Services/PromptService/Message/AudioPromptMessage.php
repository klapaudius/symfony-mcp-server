<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents an audio message in a prompt.
 *
 * This class encapsulates audio data encoded in base64 format along with its MIME type.
 * Used when a prompt needs to return audio content as part of its messages.
 */
final class AudioPromptMessage extends AbstractPromptMessage
{
    /**
     * Creates a new audio prompt message.
     *
     * @param  string  $base64EncodedData  The audio data encoded in base64 format
     * @param  string  $mimeType  The MIME type of the audio (e.g., 'audio/mpeg', 'audio/wav')
     */
    public function __construct(string $base64EncodedData, private readonly string $mimeType, string $role = PromptMessageInterface::ROLE_USER)
    {
        $this->setRole($role);
        $this->setType('audio');
        $this->setKey('data');
        $this->setValue($base64EncodedData);
    }

    /**
     * {@inheritDoc}
     */
    public function getSanitizedMessage(): array
    {
        return [
            'role' => $this->getRole(),
            'content' => [
                'type' => $this->getType(),
                $this->getKey() => $this->getValue(),
                'mimeType' => $this->mimeType,
            ],
        ];
    }
}
