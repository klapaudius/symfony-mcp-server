<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\ResourceService\ResourceInterface;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;

class ResourcesReadHandler implements RequestHandler
{
    private const TEXT_MIME_TYPES = [
        // Supported text-based MIME types
        'text/plain',
        'text/html',
        'text/css',
        'text/javascript',
        'application/json',
        'application/javascript',
        'application/xml',
        'application/xhtml+xml',
        'application/atom+xml',
        'application/rss+xml',
        'image/svg+xml',
        'text/csv',
    ];

    private ResourceRepository $resourceRepository;

    public function __construct(ResourceRepository $resourceRepository)
    {
        $this->resourceRepository = $resourceRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'resources/read';
    }

    /**
     * Executes a process based on the specified method and message ID, with optional parameters.
     *
     * @param  string  $method  The method to be executed.
     * @param  string  $clientId  The identifier of the client associated with the execution.
     * @param  string|int  $messageId  The identifier of the message associated with the execution.
     * @param  array|null  $params  Optional parameters for the execution process.
     * @return array An array containing the resource's content information if successful; returns an empty array if no resource is found.
     */
    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        $resource = $this->resourceRepository->getResource($params['uri']);

        if (! $resource) {
            return [];
        }

        return [
            'contents' => [
                [
                    'uri' => $resource->getUri(),
                    'mimeType' => $resource->getMimeType(),
                    $this->getContentType($resource) => $resource->getData(),
                ],
            ],
        ];
    }

    /**
     * Retrieves the content type that will be used for the response (e.g. "text" or "blob").
     *
     * @return string The content type.
     */
    private function getContentType(ResourceInterface $resource): string
    {
        return in_array($resource->getMimeType(), self::TEXT_MIME_TYPES, true) ? 'text' : 'blob';
    }
}
