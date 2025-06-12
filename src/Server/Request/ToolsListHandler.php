<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;

class ToolsListHandler implements RequestHandler
{
    private ToolRepository $toolRepository;

    public function __construct(ToolRepository $toolRepository)
    {
        $this->toolRepository = $toolRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'tools/list';
    }

    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        return [
            'tools' => $this->toolRepository->getToolSchemas(),
        ];
    }
}
