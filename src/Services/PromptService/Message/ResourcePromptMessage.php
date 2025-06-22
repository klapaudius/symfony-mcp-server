<?php

namespace KLP\KlpMcpServer\Services\PromptService\Message;

/**
 * Represents a resource message from a prompt.
 *
 * This class encapsulates resource data with URI, MIME type, and text content.
 * Used when a prompt needs to return resource references as part of its messages.
 */
final class ResourcePromptMessage extends AbstractPromptMessage
{
    /**
     * Creates a new resource prompt message.
     *
     * @param string $uri The URI of the resource
     * @param string $mimeType The MIME type of the resource (e.g., 'text/plain', 'application/json')
     * @param string $value The text content of the resource
     */
    public function __construct(private readonly string $uri, private readonly string $mimeType, string $value, string $role = PromptMessageInterface::ROLE_USER)
    {
        $this->setRole($role);
        $this->setType('resource');
        $this->setKey('resource');
        $this->setValue($value);
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
                $this->getKey() => [
                    'uri' => $this->uri,
                    'mimeType' => $this->mimeType,
                    'text' => $this->getValue(),
                ],
            ],
        ];
    }
}
