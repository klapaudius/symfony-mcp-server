<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents an image message in a prompt.
 *
 * This class encapsulates image data encoded in base64 format along with its MIME type.
 * Used when a prompt needs to return image content as part of its messages.
 */
final class ImagePromptMessage extends AbstractPromptMessage
{
    /**
     * Creates a new image prompt message.
     *
     * @param  string  $base64EncodedData  The image data encoded in base64 format
     * @param  string  $mimeType  The MIME type of the image (e.g., 'image/jpeg', 'image/png')
     */
    public function __construct(string $base64EncodedData, private readonly string $mimeType, string $role = PromptMessageInterface::ROLE_USER)
    {
        $this->setRole($role);
        $this->setType('image');
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
