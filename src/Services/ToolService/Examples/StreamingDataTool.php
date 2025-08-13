<?php

namespace KLP\KlpMcpServer\Services\ToolService\Examples;

use KLP\KlpMcpServer\Services\ProgressService\ProgressNotifierInterface;
use KLP\KlpMcpServer\Services\ToolService\Annotation\ToolAnnotation;
use KLP\KlpMcpServer\Services\ToolService\Result\TextToolResult;
use KLP\KlpMcpServer\Services\ToolService\Result\ToolResultInterface;
use KLP\KlpMcpServer\Services\ToolService\StreamableToolInterface;

class StreamingDataTool implements StreamableToolInterface
{
    private ?ProgressNotifierInterface $progressNotifier = null;

    private string|int|null $progressToken = null;

    public function getName(): string
    {
        return 'stream-data';
    }

    public function getDescription(): string
    {
        return 'Demonstrates streaming data processing with progress notifications. Simulates processing a dataset with real-time progress updates.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message to stream',
                ],
                'chunks' => [
                    'type' => 'integer',
                    'description' => 'Number of chunks to stream',
                    'minimum' => 1,
                    'maximum' => 10,
                    'default' => 5,
                ],
                'delay' => [
                    'type' => 'integer',
                    'description' => 'Delay between chunks in milliseconds',
                    'minimum' => 100,
                    'maximum' => 2000,
                    'default' => 500,
                ],
            ],
            'required' => ['message'],
        ];
    }

    public function getAnnotations(): ToolAnnotation
    {
        return new ToolAnnotation(
            readOnlyHint: true,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false
        );
    }

    public function execute(array $arguments): ToolResultInterface
    {
        $message = $arguments['message'] ?? 'Hello, streaming world!';
        $datasetSize = $arguments['chunks'] ?? 5;
        $processingDelay = $arguments['delay'] ?? 500;
        $chunks = '';

        // Simulate processing with progress updates
        for ($i = 1; $i <= $datasetSize; $i++) {
            // Simulate processing work
            usleep($processingDelay * 1000); // Convert ms to microseconds
            $chunks .= "$message - Chunk $i/$datasetSize\n";

            // Send progress notification if streaming
            if ($this->progressNotifier && $this->isStreaming()) {
                // Note: This assumes the progress token is available through some mechanism
                // In real implementation, you'd need to pass the progress token from the tool execution context
                try {
                    $this->progressNotifier->sendProgress(
                        progress: $i,
                        total: $datasetSize,
                        message: $message
                    );
                } catch (\Exception $e) {
                    // Continue processing even if progress notification fails
                    error_log('Progress notification failed: '.$e->getMessage());
                }
            }
        }

        return new TextToolResult($chunks);
    }

    public function isStreaming(): bool
    {
        return true;
    }

    public function setProgressNotifier(ProgressNotifierInterface $progressNotifier): void
    {
        $this->progressNotifier = $progressNotifier;
    }

    public function setProgressToken(string|int|null $progressToken): void
    {
        $this->progressToken = $progressToken;
    }
}
