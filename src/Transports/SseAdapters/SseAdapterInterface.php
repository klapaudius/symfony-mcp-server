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
}
