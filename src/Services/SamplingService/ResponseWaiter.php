<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Services\SamplingService;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles waiting for async responses to sampling requests
 */
class ResponseWaiter
{
    /**
     * @var array<string|int, array{response: mixed|null, timestamp: int}>
     */
    private array $pendingResponses = [];

    /**
     * @var array<string, callable>
     */
    private array $responseCallbacks = [];

    private int $defaultTimeout;

    public function __construct(
        private LoggerInterface $logger,
        private ?SseAdapterInterface $adapter = null,
        int $defaultTimeout = 30
    ) {
        $this->defaultTimeout = $defaultTimeout;
    }

    /**
     * Register a request and wait for its response
     *
     * @param  string  $messageId  The unique message ID
     * @param  int|null  $timeout  Timeout in seconds (null for default)
     * @return mixed The response data
     *
     * @throws JsonRpcErrorException If timeout occurs or response is an error
     */
    public function waitForResponse(string $messageId, ?int $timeout = null): mixed
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        $startTime = time();

        // Register the pending response (both in memory and persistent storage)
        $responseData = [
            'response' => null,
            'timestamp' => $startTime,
        ];

        $this->pendingResponses[$messageId] = $responseData;

        // Store in adapter if available
        if ($this->adapter !== null) {
            try {
                $this->adapter->storePendingResponse($messageId, $responseData);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to store pending response in adapter', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->debug('Waiting for response', [
            'messageId' => $messageId,
            'timeout' => $timeout,
        ]);

        // Poll for response with shorter intervals and maximum iterations
        $maxIterations = ($timeout * 1000) / 50; // 50ms intervals
        $iterations = 0;

        while ($iterations < $maxIterations) {
            // Check if response has arrived (check both memory and adapter)
            $response = $this->checkForResponse($messageId);
            if ($response !== null) {
                // Clean up
                unset($this->pendingResponses[$messageId]);
                if ($this->adapter !== null) {
                    try {
                        $this->adapter->removePendingResponse($messageId);
                    } catch (\Throwable $e) {
                        $this->logger->warning('Failed to remove pending response from adapter', [
                            'messageId' => $messageId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

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

        // Timeout reached - clean up
        unset($this->pendingResponses[$messageId]);
        if ($this->adapter !== null) {
            try {
                $this->adapter->removePendingResponse($messageId);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to remove timed out response from adapter', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->error('Response timeout', [
            'messageId' => $messageId,
            'timeout' => $timeout,
        ]);

        throw new JsonRpcErrorException(
            'Sampling request timed out after '.$timeout.' seconds',
            JsonRpcErrorCode::INTERNAL_ERROR
        );
    }

    /**
     * Check for a response (both in memory and adapter)
     *
     * @param  string  $messageId  The message ID to check
     * @return mixed The response data or null if not found
     */
    private function checkForResponse(string $messageId): mixed
    {
        // Check in-memory first
        if (isset($this->pendingResponses[$messageId]) && $this->pendingResponses[$messageId]['response'] !== null) {
            return $this->pendingResponses[$messageId]['response'];
        }

        // Check adapter if available
        if ($this->adapter !== null) {
            try {
                $storedData = $this->adapter->getPendingResponse($messageId);
                if ($storedData !== null && isset($storedData['response']) && $storedData['response'] !== null) {
                    // Update in-memory cache
                    $this->pendingResponses[$messageId] = $storedData;

                    return $storedData['response'];
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to check adapter for response', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Handle an incoming response message
     *
     * @param  array<string, mixed>  $message  The response message
     */
    public function handleResponse(array $message): void
    {
        if (! isset($message['id']) || ! is_string($message['id'])) {
            return;
        }

        $messageId = $message['id'];

        // Check if this is a response we're waiting for (check both memory and adapter)
        if (! $this->isWaitingFor($messageId)) {
            return;
        }

        $this->logger->debug('Handling response', [
            'messageId' => $messageId,
        ]);

        $responseValue = null;

        // Check for error response
        if (isset($message['error'])) {
            $error = $message['error'];
            $errorMessage = $error['message'] ?? 'Unknown error';
            $errorCode = $error['code'] ?? JsonRpcErrorCode::INTERNAL_ERROR->value;

            $responseValue = new JsonRpcErrorException(
                $errorMessage,
                JsonRpcErrorCode::tryFrom($errorCode) ?? JsonRpcErrorCode::INTERNAL_ERROR
            );
        } else {
            // Store the result
            $responseValue = $message['result'] ?? null;
        }

        // Update in-memory storage
        if (isset($this->pendingResponses[$messageId])) {
            $this->pendingResponses[$messageId]['response'] = $responseValue;
        }

        // Update adapter storage
        if ($this->adapter !== null) {
            try {
                $storedData = $this->adapter->getPendingResponse($messageId);
                if ($storedData !== null) {
                    $storedData['response'] = $responseValue;
                    $this->adapter->storePendingResponse($messageId, $storedData);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to update response in adapter', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Execute any registered callback
        if (isset($this->responseCallbacks[$messageId])) {
            $callback = $this->responseCallbacks[$messageId];
            unset($this->responseCallbacks[$messageId]);

            try {
                $callback($responseValue);
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
     * @param  string  $messageId  The message ID to wait for
     * @param  callable  $callback  The callback to execute with the response
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
    public function isWaitingFor(string|int $messageId): bool
    {
        // Check in-memory first
        if (isset($this->pendingResponses[$messageId])) {
            return true;
        }

        // Check adapter if available
        if ($this->adapter !== null) {
            try {
                return $this->adapter->hasPendingResponse((string) $messageId);
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to check adapter for pending response', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }
}
