<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\StructuredToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\Schema\PropertyType;
use KLP\KlpMcpServer\Services\ToolService\Schema\SchemaProperty;
use KLP\KlpMcpServer\Services\ToolService\Schema\StructuredSchema;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;
use Symfony\Component\HttpKernel\Kernel;

class VersionCheckTool implements StreamableToolInterface
{
    public function getName(): string
    {
        return 'check-version';
    }

    public function getDescription(): string
    {
        return 'Check the current Symfony version.';
    }

    public function getInputSchema(): StructuredSchema
    {
        return new StructuredSchema;
    }

    public function getOutputSchema(): ?StructuredSchema
    {
        return new StructuredSchema(
            new SchemaProperty(
                name: 'version',
                type: PropertyType::STRING,
                description: 'The current Symfony version',
                required: true,
            ),
            new SchemaProperty(
                name: 'date',
                type: PropertyType::STRING,
                description: 'The current date and time',
                required: true,
            )
        );
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            title: 'Check Symfony Version',
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        return new StructuredToolResult([
            'version' => Kernel::VERSION,
            'date' => (new \DateTime('now'))->format('Y-m-d H:i:s'),
        ]);
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
