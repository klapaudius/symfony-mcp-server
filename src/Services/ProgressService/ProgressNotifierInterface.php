<?php

namespace KLP\KlpMcpServer\Services\ProgressService;

/**
 * ProgressNotifier
 *
 * Handles sending progress notifications according to MCP specification.
 * Progress notifications allow tracking long-running operations.
 *
 * @internal
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/utilities/progress
 */
interface ProgressNotifierInterface
{
    /**
     * Sends a progress notification to the client.
     *
     * @param  float|int  $progress  Current progress value (must increase with each notification)
     * @param  float|int|null  $total  Optional total value for the operation
     * @param  string|null  $message  Optional human-readable progress message
     *
     * @throws ProgressTokenException If the progress doesn't increase
     */
    public function sendProgress(float|int $progress, float|int|null $total = null, ?string $message = null): void;
}
