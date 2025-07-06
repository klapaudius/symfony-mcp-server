<?php

namespace KLP\KlpMcpServer\Transports\SseAdapters;

/**
 * Interface SseAdapterInterface
 *
 * Defines the contract for SSE message queue adapters in the MCP server.
 * These adapters handle message queuing for Server-Sent Events connections.
 * Implementations include Redis, NATS, and InMemory adapters.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/transports
 */
interface SseAdapterInterface
{
    /**
     * Add a message to the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @param  string  $message  The message to be queued
     *
     * @throws SseAdapterException If the message cannot be added to the queue
     */
    public function pushMessage(string $clientId, string $message): void;

    /**
     * Remove all messages for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     *
     * @throws SseAdapterException If the messages cannot be removed
     */
    public function removeAllMessages(string $clientId): void;

    /**
     * Receive all messages for a specific client without removing them
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return array<string> Array of messages
     *
     * @throws SseAdapterException If the messages cannot be retrieved
     */
    public function receiveMessages(string $clientId): array;

    /**
     * Pop the oldest message from the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return string|null The message or null if the queue is empty
     *
     * @throws SseAdapterException If the message cannot be popped
     */
    public function popMessage(string $clientId): ?string;

    /**
     * Check if there are any messages in the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return bool True if there are messages, false otherwise
     */
    public function hasMessages(string $clientId): bool;

    /**
     * Get the number of messages in the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return int The number of messages
     */
    public function getMessageCount(string $clientId): int;

    /**
     * Store the last pong response timestamp for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @param  int|null  $timestamp  The timestamp to store (defaults to current time if null)
     *
     * @throws SseAdapterException If the timestamp cannot be stored
     */
    public function storeLastPongResponseTimestamp(string $clientId, ?int $timestamp = null): void;

    /**
     * Get the last pong response timestamp for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return int|null The timestamp or null if no timestamp is stored
     *
     * @throws SseAdapterException If the timestamp cannot be retrieved
     */
    public function getLastPongResponseTimestamp(string $clientId): ?int;

    /**
     * Store sampling capability information for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @param  bool  $hasSamplingCapability  Whether the client supports sampling
     *
     * @throws SseAdapterException If the sampling capability cannot be stored
     */
    public function storeSamplingCapability(string $clientId, bool $hasSamplingCapability): void;

    /**
     * Check if a specific client has sampling capability
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return bool True if the client supports sampling, false otherwise
     *
     * @throws SseAdapterException If the sampling capability cannot be retrieved
     */
    public function hasSamplingCapability(string $clientId): bool;

    /**
     * Store pending response data for a specific message ID
     *
     * @param  string  $messageId  The unique message ID
     * @param  array  $responseData  The response data to store
     *
     * @throws SseAdapterException If the response data cannot be stored
     */
    public function storePendingResponse(string $messageId, array $responseData): void;

    /**
     * Get pending response data for a specific message ID
     *
     * @param  string  $messageId  The unique message ID
     * @return array|null The response data or null if not found
     *
     * @throws SseAdapterException If the response data cannot be retrieved
     */
    public function getPendingResponse(string $messageId): ?array;

    /**
     * Remove pending response data for a specific message ID
     *
     * @param  string  $messageId  The unique message ID
     *
     * @throws SseAdapterException If the response data cannot be removed
     */
    public function removePendingResponse(string $messageId): void;

    /**
     * Check if there is pending response data for a specific message ID
     *
     * @param  string  $messageId  The unique message ID
     * @return bool True if there is pending response data, false otherwise
     */
    public function hasPendingResponse(string $messageId): bool;

    /**
     * Clean up old pending responses that have exceeded the maximum age
     *
     * @param  int  $maxAge  Maximum age in seconds
     * @return int Number of responses cleaned up
     *
     * @throws SseAdapterException If the cleanup cannot be performed
     */
    public function cleanupOldPendingResponses(int $maxAge): int;
}
