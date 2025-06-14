<?php

namespace KLP\KlpMcpServer\Server\Request;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierRepository;
use KLP\KlpMcpServer\Services\ToolService\Result\AudioToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\CollectionToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ImageToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ResourceToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use KLP\KlpMcpServer\Services\ToolService\ToolParamsValidator;
use KLP\KlpMcpServer\Services\ToolService\ToolRepository;

class ToolsCallHandler implements RequestHandler
{
    private ToolRepository $toolRepository;

    private ProgressNotifierRepository $progressNotifierRepository;

    public function __construct(ToolRepository $toolRepository, ProgressNotifierRepository $progressNotifierRepository)
    {
        $this->toolRepository = $toolRepository;
        $this->progressNotifierRepository = $progressNotifierRepository;
    }

    public function isHandle(string $method): bool
    {
        return $method === 'tools/call';
    }

    /**
     * Executes a specified method with provided parameters and returns the result.
     *
     * @param  string  $method  The method to be executed.
     * @param  string|int  $clientId  The ID of the client. Used for progress notifications.
     * @param  string|int  $messageId  The ID of the request message. Used for response identification.
     * @param  array|null  $params  An associative array of parameters required for execution. Must include 'name' as the tool identifier and optionally 'arguments'.
     * @return array The response array containing the execution result, which may vary based on the method.
     *
     * @throws JsonRpcErrorException If the tool name is missing or the tool is not found
     * @throws ToolParamsValidatorException If the provided arguments are invalid.
     */
    public function execute(string $method, string $clientId, string|int $messageId, ?array $params = null): array
    {
        $name = $params['name'] ?? null;
        if ($name === null) {
            throw new JsonRpcErrorException(message: 'Tool name is required', code: JsonRpcErrorCode::INVALID_REQUEST, data: $params);
        }

        $tool = $this->toolRepository->getTool($name);
        if (! $tool) {
            throw new JsonRpcErrorException(message: "Tool '{$name}' not found", code: JsonRpcErrorCode::METHOD_NOT_FOUND, data: $params);
        }

        $arguments = $params['arguments'] ?? [];
        $progressToken = $params['_meta']['progressToken'] ?? null;

        ToolParamsValidator::validate($tool->getInputSchema(), $arguments);

        if ($tool instanceof StreamableToolInterface
            && $tool->isStreaming()
            && $progressToken
        ) {
            $progressNotifier = $this->progressNotifierRepository->registerToken($progressToken, $clientId);
            $tool->setProgressNotifier($progressNotifier);
        }
        $result = $tool->execute($arguments);

        $this->progressNotifierRepository->unregisterToken($progressToken);

        if ($method === 'tools/call') {
            if (! $result instanceof ToolResultInterface) {
                trigger_deprecation(
                    'klapaudius/symfony-mcp-server',
                    '1.2',
                    sprintf(
                        'The return value of the "%s" method must be an instance of "%s", please use one of this classes instead: "%s".',
                        get_class($tool).'::execute',
                        ToolResultInterface::class,
                        implode(', ', [
                            TextToolResult::class,
                            ImageToolResult::class,
                            AudioToolResult::class,
                            ResourceToolResult::class,
                        ])
                    ));

                $result = new TextToolResult(is_string($result) ? $result : json_encode($result));
            }
            $content = $result instanceof CollectionToolResult
                ? $result->getSanitizedResult()
                : [ $result->getSanitizedResult() ];

            return [
                'content' => $content,
            ];
        } else {
            return [
                'result' => $result,
            ];
        }
    }
}
