<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\ToolInterface;

class HelloWorldTool implements ToolInterface
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

    public function execute(array $arguments): string
    {
        $name = $arguments['name'] ?? 'MCP';

        return "Hello, HelloWorld `{$name}` developer.";
    }
}
