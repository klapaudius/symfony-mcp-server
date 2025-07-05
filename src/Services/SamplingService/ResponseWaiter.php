<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use Psr\Log\LoggerInterface;

/**
 * Handles waiting for async responses to sampling requests
 */
class ResponseWaiter
{
    /**
     * @var array<string, array{response: mixed|null, timestamp: int}>
     */
    private array $pendingResponses = [];

    /**
     * @var array<string, callable>
     */
    private array $responseCallbacks = [];

    private int $defaultTimeout;

    public function __construct(
        private LoggerInterface $logger,
        int $defaultTimeout = 30
    ) {
        $this->defaultTimeout = $defaultTimeout;
    }

    /**
     * Register a request and wait for its response
     *
     * @param string $messageId The unique message ID
     * @param int|null $timeout Timeout in seconds (null for default)
     * @return mixed The response data
     * @throws JsonRpcErrorException If timeout occurs or response is an error
     */
    public function waitForResponse(string $messageId, int|null $timeout = null): mixed
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        $startTime = time();

        // Register the pending response
        $this->pendingResponses[$messageId] = [
            'response' => null,
            'timestamp' => $startTime,
        ];

        $this->logger->debug('Waiting for response', [
            'messageId' => $messageId,
            'timeout' => $timeout,
        ]);

        // Poll for response with shorter intervals and maximum iterations
        $maxIterations = ($timeout * 1000) / 50; // 50ms intervals
        $iterations = 0;

        while ($iterations < $maxIterations) {
            // Check if response has arrived
            if (isset($this->pendingResponses[$messageId]) && $this->pendingResponses[$messageId]['response'] !== null) {
                $response = $this->pendingResponses[$messageId]['response'];
                unset($this->pendingResponses[$messageId]);

                $this->logger->debug('Response received', [
                    'messageId' => $messageId,
                    'elapsed' => time() - $startTime,
                ]);

                return $response;
            }

            // Check timeout
            if (time() - $startTime >= $timeout) {
                break;
            }

            // Sleep briefly to avoid busy waiting
            usleep(50000); // 50ms
            $iterations++;
        }

        // Timeout reached
        unset($this->pendingResponses[$messageId]);

        $this->logger->error('Response timeout', [
            'messageId' => $messageId,
            'timeout' => $timeout,
        ]);

        throw new JsonRpcErrorException(
            'Sampling request timed out after ' . $timeout . ' seconds',
            JsonRpcErrorCode::INTERNAL_ERROR
        );
    }

    /**
     * Handle an incoming response message
     *
     * @param array<string, mixed> $message The response message
     */
    public function handleResponse(array $message): void
    {
        if (!isset($message['id']) || !is_string($message['id'])) {
            return;
        }

        $messageId = $message['id'];

        // Check if this is a response we're waiting for
        if (!isset($this->pendingResponses[$messageId])) {
            return;
        }

        $this->logger->debug('Handling response', [
            'messageId' => $messageId,
        ]);

        // Check for error response
        if (isset($message['error'])) {
            $error = $message['error'];
            $errorMessage = $error['message'] ?? 'Unknown error';
            $errorCode = $error['code'] ?? JsonRpcErrorCode::INTERNAL_ERROR->value;

            $this->pendingResponses[$messageId]['response'] = new JsonRpcErrorException(
                $errorMessage,
                JsonRpcErrorCode::tryFrom($errorCode) ?? JsonRpcErrorCode::INTERNAL_ERROR
            );
            return;
        }

        // Store the result
        $this->pendingResponses[$messageId]['response'] = $message['result'] ?? null;

        // Execute any registered callback
        if (isset($this->responseCallbacks[$messageId])) {
            $callback = $this->responseCallbacks[$messageId];
            unset($this->responseCallbacks[$messageId]);

            try {
                $callback($message['result'] ?? null);
            } catch (\Throwable $e) {
                $this->logger->error('Response callback error', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Register a callback to be executed when a response is received
     *
     * @param string $messageId The message ID to wait for
     * @param callable $callback The callback to execute with the response
     */
    public function registerCallback(string $messageId, callable $callback): void
    {
        $this->responseCallbacks[$messageId] = $callback;
    }

    /**
     * Clean up old pending responses
     */
    public function cleanup(): void
    {
        $now = time();
        $maxAge = $this->defaultTimeout * 2;

        foreach ($this->pendingResponses as $messageId => $data) {
            if ($now - $data['timestamp'] > $maxAge) {
                unset($this->pendingResponses[$messageId]);
                unset($this->responseCallbacks[$messageId]);

                $this->logger->warning('Cleaned up stale response', [
                    'messageId' => $messageId,
                    'age' => $now - $data['timestamp'],
                ]);
            }
        }
    }

    /**
     * Check if we're waiting for a specific message ID
     */
    public function isWaitingFor(string $messageId): bool
    {
        return isset($this->pendingResponses[$messageId]);
    }
}
