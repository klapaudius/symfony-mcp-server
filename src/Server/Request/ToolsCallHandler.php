<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;

class ToolsCallHandler implements RequestHandler
{
    private ToolRepository $toolRepository;

    public function __construct(ToolRepository $toolRepository)
    {
        $this->toolRepository = $toolRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'tools/call' || $method === 'tools/execute';
    }

    /**
     * Executes a specified method with provided parameters and returns the result.
     *
     * @param  string  $method  The method to be executed.
     * @param  array|null  $params  An associative array of parameters required for execution. Must include 'name' as the tool identifier and optionally 'arguments'.
     * @return array The response array containing the execution result, which may vary based on the method.
     *
     * @throws JsonRpcErrorException If the tool name is missing or the tool is not found
     * @throws ToolParamsValidatorException If the provided arguments are invalid.
     */
    public function execute(string $method, ?array $params = null): array
    {
        $name = $params['name'] ?? null;
        if ($name === null) {
            throw new JsonRpcErrorException(message: 'Tool name is required', code: JsonRpcErrorCode::INVALID_REQUEST, data: $params);
        }

        $tool = $this->toolRepository->getTool($name);
        if (! $tool) {
            throw new JsonRpcErrorException(message: "Tool '{$name}' not found", code: JsonRpcErrorCode::METHOD_NOT_FOUND, data:$params);
        }

        $arguments = $params['arguments'] ?? [];

        ToolParamsValidator::validate($tool->getInputSchema(), $arguments);

        $result = $tool->execute($arguments);

        if ($method === 'tools/call') {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result),
                    ],
                ],
            ];
        } else {
            return [
                'result' => $result,
            ];
        }
    }
}
