<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class HelloWorldTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'hello-world';
    }

    public function getDescription(): string
    {
        return 'Say HelloWorld developer.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Developer Name',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation;
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $name = $arguments['name'] ?? 'MCP';

        return new  TextToolResult("Hello, HelloWorld `{$name}` developer.");
    }

    public function isStreaming(): bool
    {
        return false;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        // nothing to do here this tool is not streaming.
    }
}
