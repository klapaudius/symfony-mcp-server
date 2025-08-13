<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;

class PromptsListHandler implements RequestHandler
{
    private PromptRepository $promptRepository;

    public function __construct(PromptRepository $promptRepository)
    {
        $this->promptRepository = $promptRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'prompts/list';
    }

    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        return [
            'prompts' => $this->promptRepository->getPromptSchemas(),
        ];
    }
}
