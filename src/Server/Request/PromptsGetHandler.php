<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\PromptService\PromptRepository;
use KLP\KlpMcpServer\Services\PromptService\SamplingAwarePromptInterface;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;

class PromptsGetHandler implements RequestHandler
{
    private PromptRepository $promptRepository;

    private ?SamplingClient $samplingClient;

    public function __construct(PromptRepository $promptRepository, ?SamplingClient $samplingClient)
    {
        $this->promptRepository = $promptRepository;
        $this->samplingClient = $samplingClient;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'prompts/get';
    }

    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        if (! isset($params['name']) || ! is_string($params['name'])) {
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

        // Inject sampling client if the prompt supports it
        if ($prompt instanceof SamplingAwarePromptInterface && $this->samplingClient !== null) {
            $this->samplingClient->setCurrentClientId($clientId);
            $prompt->setSamplingClient($this->samplingClient);
        }

        return [
            'description' => $prompt->getDescription(),
            'messages' => $prompt->getMessages($arguments)->getSanitizedMessages(),
        ];
    }
}
