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
        return in_array($method, ['resources/list', 'resources/templates/list']);
    }

    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        if ($method === 'resources/templates/list') {
            return [
                'resourceTemplates' => $this->resourceRepository->getResourceTemplateSchemas(),
            ];
        } else {
            return [
                'resources' => $this->resourceRepository->getResourceSchemas(),
            ];
        }
    }
}
