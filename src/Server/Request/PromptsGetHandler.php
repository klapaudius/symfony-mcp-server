<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;

class PromptsGetHandler implements RequestHandler
{
    private PromptRepository $promptRepository;

    public function __construct(PromptRepository $promptRepository)
    {
        $this->promptRepository = $promptRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'prompts/get';
    }

    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        if (!isset($params['name']) || !is_string($params['name'])) {
            throw new JsonRpcErrorException(
                message: 'Prompt name is required',
                code: JsonRpcErrorCode::INVALID_PARAMS
            );
        }

        $prompt = $this->promptRepository->getPrompt($params['name']);

        if ($prompt === null) {
            throw new JsonRpcErrorException(
                message: "Prompt '{$params['name']}' not found",
                code: JsonRpcErrorCode::INVALID_PARAMS
            );
        }

        $arguments = $params['arguments'] ?? [];

        return [
            'description' => $prompt->getDescription(),
            'messages' => $prompt->getMessages($arguments)->getSanitizedMessages(),
        ];
    }
}
