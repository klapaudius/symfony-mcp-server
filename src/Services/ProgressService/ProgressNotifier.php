<?php

namespace KLP\KlpMcpServer\Services\ProgressService;

/**
 * ProgressNotifier
 *
 * Handles sending progress notifications according to MCP specification.
 * Progress notifications allow tracking long-running operations.
 *
 * @internal
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/utilities/progress
 */
class ProgressNotifier
{
    private array $handlers = [];
    private int $lastProgress = -1;

    public function __construct(
        private readonly string|int $progressToken,
        callable $onProgress
    ) {
        $this->handlers[] = $onProgress;
    }

    /**
     * Sends a progress notification to the client.
     *
     * @param float|int $progress Current progress value (must increase with each notification)
     * @param float|int|null $total Optional total value for the operation
     * @param string|null $message Optional human-readable progress message
     *
     * @throws ProgressTokenException If the progress doesn't increase
     */
    public function sendProgress(
        float|int $progress,
        float|int|null $total = null,
        string|null $message = null
    ): void {

        // Validate that progress increases
        if ($progress <= $this->lastProgress) {
            throw new ProgressTokenException("Progress value must increase with each notification. Current: {$progress}, Last: {$this->lastProgress}");
        }

        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/progress',
            'params' => [
                'progressToken' => $this->progressToken,
                'progress' => $progress,
            ]
        ];

        if ($total !== null) {
            $notification['params']['total'] = $total;
        }

        if ($message !== null) {
            $notification['params']['message'] = $message;
        }

        foreach ($this->handlers as $handler) {
            $handler($notification);
        }

        // Update last progress
        $this->lastProgress = $progress;
    }
}
