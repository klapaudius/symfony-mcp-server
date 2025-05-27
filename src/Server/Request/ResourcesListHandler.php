<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\ResourceService\ResourceRepository;

class ResourcesListHandler implements RequestHandler
{
    private ResourceRepository $resourceRepository;

    public function __construct(ResourceRepository $resourceRepository)
    {
        $this->resourceRepository = $resourceRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'resources/list';
    }

    public function execute(string $method, string|int $messageId, ?array $params = null): array
    {
        return [
            'resources' => $this->resourceRepository->getResourceSchemas(),
        ];
    }
}
