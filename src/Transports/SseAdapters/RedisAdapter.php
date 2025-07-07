<?php

namespace KLP\KlpMcpServer\Transports\SseAdapters;

use Exception;
use Psr\Log\LoggerInterface;
use Redis;

/**
 * Redis Adapter for SSE Transport
 *
 * Implements the SSE Adapter interface using Redis as the backend storage.
 * This adapter uses Redis lists to store messages for each client.
 *
 * @see https://modelcontextprotocol.io/docs/concepts/transports
 */
final class RedisAdapter implements SseAdapterInterface
{
    private const DEFAULT_PREFIX = 'mcp_sse_';

    private const DEFAULT_MESSAGE_TTL = 100;

    private const DEFAULT_REDIS_PORT = 6379;

    private const FAILED_TO_INITIALIZE = 'Failed to initialize Redis SSE Adapter: ';

    private const SAMPLING_KEY_SUFFIX = ':sampling';

    /**
     * Redis connection instance
     */
    private Redis $redis;

    /**
     * Redis key prefix for SSE messages
     */
    private string $keyPrefix;

    /**
     * Message expiration time in seconds
     */
    private int $messageTtl;

    /**
     * Constructor method to initialize the class with configuration, logger, and Redis instance
     *
     * @param  array  $config  Configuration array containing details such as 'prefix', 'ttl', and 'host'
     * @param  LoggerInterface|null  $logger  Logger instance for error and debugging logs
     * @param  Redis|null  $redis  Optional Redis instance used during tests
     * @return void
     *
     * @throws SseAdapterException If the Redis connection fails to initialize
     */
    public function __construct(
        private readonly array $config,
        private readonly ?LoggerInterface $logger,
        ?Redis $redis = null // Allow Redis to be injected
    ) {
        $this->keyPrefix = $this->config['prefix'] ?? self::DEFAULT_PREFIX;
        $this->messageTtl = (int) ($this->config['ttl'] ?? self::DEFAULT_MESSAGE_TTL);

        try {
            $this->redis = $redis ?? new Redis;
            $this->redis->connect($this->config['host'], self::DEFAULT_REDIS_PORT);
            $this->redis->setOption(Redis::OPT_PREFIX, $this->keyPrefix);
        } catch (Exception $e) {
            $this->logAndThrow(self::FAILED_TO_INITIALIZE.$e->getMessage(), $e);
        }
    }

    /**
     * Add a message to the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @param  string  $message  The message to be queued
     *
     * @throws SseAdapterException If the message cannot be added to the queue
     */
    public function pushMessage(string $clientId, string $message): void
    {
        try {
            $key = $this->generateQueueKey($clientId);
            $this->redis->rpush($key, $message);
            $this->setKeyExpiration($key);
        } catch (Exception $e) {
            $this->logAndThrow('Failed to add message to Redis queue: '.$e->getMessage(), $e);
        }
    }

    /**
     * Remove all messages for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     *
     * @throws SseAdapterException If the messages cannot be removed
     */
    public function removeAllMessages(string $clientId): void
    {
        $this->executeRedisCommand(
            fn () => $this->redis->del($this->generateQueueKey($clientId)),
            'Failed to remove messages from Redis queue'
        );
    }

    /**
     * Receive and remove all messages for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return array<string|array> Array of messages
     *
     * @throws SseAdapterException If the messages cannot be retrieved
     */
    public function receiveMessages(string $clientId): array
    {
        try {
            $key = $this->generateQueueKey($clientId);
            $messages = [];
            while (($message = $this->redis->lpop($key)) !== null && $message !== false) {
                $messages[] = $message;
            }

            return $messages;
        } catch (Exception $e) {
            $this->logAndThrow('Failed to receive messages from Redis queue: '.$e->getMessage(), $e);
        }
    }

    /**
     * Pop the oldest message from the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return string|null The message or null if the queue is empty
     *
     * @throws SseAdapterException If the message cannot be popped
     */
    public function popMessage(string $clientId): ?string
    {
        try {
            $key = $this->generateQueueKey($clientId);
            $message = $this->redis->lpop($key);

            return $message === false ? null : $message;
        } catch (Exception $e) {
            $this->logAndThrow('Failed to pop message from Redis queue: '.$e->getMessage(), $e);
        }
    }

    /**
     * Check if there are any messages in the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return bool True if there are messages, false otherwise
     */
    public function hasMessages(string $clientId): bool
    {
        $key = $this->generateQueueKey($clientId);

        return $this->getRedisKeyLength($key) > 0;
    }

    /**
     * Get the number of messages in the queue for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return int The number of messages
     */
    public function getMessageCount(string $clientId): int
    {
        $key = $this->generateQueueKey($clientId);

        return $this->getRedisKeyLength($key);
    }

    /**
     * Store the last pong response timestamp for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @param  int|null  $timestamp  The timestamp to store (defaults to current time if null)
     *
     * @throws SseAdapterException If the timestamp cannot be stored
     */
    public function storeLastPongResponseTimestamp(string $clientId, ?int $timestamp = null): void
    {
        $this->executeRedisCommand(
            function () use ($clientId, $timestamp) {
                $key = $this->generateQueueKey($clientId).':last_pong';
                $this->redis->set($key, $timestamp ?? time());
                $this->setKeyExpiration($key);
            },
            'Failed to store last pong timestamp'
        );
    }

    /**
     * Get the last pong response timestamp for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return int|null The timestamp or null if no timestamp is stored
     *
     * @throws SseAdapterException If the timestamp cannot be retrieved
     */
    public function getLastPongResponseTimestamp(string $clientId): ?int
    {
        try {
            $key = $this->generateQueueKey($clientId).':last_pong';
            $timestamp = $this->redis->get($key);

            return $timestamp === false ? null : (int) $timestamp;
        } catch (Exception $e) {
            $this->logAndThrow('Failed to get last pong timestamp: '.$e->getMessage(), $e);
        }
    }

    /**
     * Store sampling capability information for a specific client
     *
     * @param  string  $clientId  The unique identifier for the client
     * @param  bool  $hasSamplingCapability  Whether the client supports sampling
     *
     * @throws SseAdapterException If the sampling capability cannot be stored
     */
    public function storeSamplingCapability(string $clientId, bool $hasSamplingCapability): void
    {
        $this->executeRedisCommand(
            function () use ($clientId, $hasSamplingCapability) {
                $key = $this->generateQueueKey($clientId).self::SAMPLING_KEY_SUFFIX;
                $this->redis->set($key, $hasSamplingCapability ? '1' : '0');
                $this->setKeyExpiration($key, 60 * 60 * 24);
            },
            'Failed to store sampling capability'
        );
    }

    /**
     * Check if a specific client has sampling capability
     *
     * @param  string  $clientId  The unique identifier for the client
     * @return bool True if the client supports sampling, false otherwise
     *
     * @throws SseAdapterException If the sampling capability cannot be retrieved
     */
    public function hasSamplingCapability(string $clientId): bool
    {
        try {
            $key = $this->generateQueueKey($clientId).self::SAMPLING_KEY_SUFFIX;
            $capability = $this->redis->get($key);

            return $capability === '1';
        } catch (Exception $e) {
            $this->logAndThrow('Failed to retrieve sampling capability: '.$e->getMessage(), $e);
        }
    }

    /**
     * Get the Redis key for a client's message queue
     *
     * @param  string  $clientId  The client ID
     * @return string The Redis key
     */
    private function generateQueueKey(string $clientId): string
    {
        return "$this->keyPrefix:client:$clientId";
    }

    /**
     * Set the expiration time for the specified key in Redis
     *
     * @param  string  $key  The key for which the expiration time will be set
     * @param  int|null  $customTtl  The custom expiration time in seconds (defaults to the message TTL)
     *
     * @see https://redis.io/commands/expire
     */
    private function setKeyExpiration(string $key, ?int $customTtl = null): void
    {
        $this->redis->expire($key, $customTtl ?? $this->messageTtl);
    }

    /**
     * Retrieve the length of a Redis list by its key
     *
     * @param  string  $key  The key of the Redis list
     * @return int The length of the Redis list, or 0 if an error occurs
     */
    private function getRedisKeyLength(string $key): int
    {
        try {
            return (int) $this->redis->llen($key);
        } catch (Exception $e) {
            $this->logger?->error('Failed to get message count: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Executes a Redis command and handles potential exceptions.
     *
     * @param  callable  $command  The Redis command to be executed
     * @param  string  $errorMessage  The error message to log and throw in case of failure
     *
     * @throws SseAdapterException
     */
    private function executeRedisCommand(callable $command, string $errorMessage): mixed
    {
        try {
            return $command();
        } catch (Exception $e) {
            $this->logAndThrow($errorMessage.': '.$e->getMessage(), $e);
        }
    }

    /**
     * Get the Redis key for a pending response
     *
     * @param  string  $messageId  The message ID
     * @return string The Redis key
     */
    private function generatePendingResponseKey(string $messageId): string
    {
        return "$this->keyPrefix:pending_response:$messageId";
    }

    /**
     * {@inheritDoc}
     */
    public function storePendingResponse(string $messageId, array $responseData): void
    {
        $this->executeRedisCommand(
            function () use ($messageId, $responseData) {
                $key = $this->generatePendingResponseKey($messageId);
                $this->redis->set($key, json_encode($responseData));
                $this->setKeyExpiration($key);

                $this->logger?->debug('Stored pending response', [
                    'messageId' => $messageId,
                    'key' => $key,
                ]);
            },
            'Failed to store pending response'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingResponse(string $messageId): ?array
    {
        return $this->executeRedisCommand(
            function () use ($messageId) {
                $key = $this->generatePendingResponseKey($messageId);
                $data = $this->redis->get($key);

                if ($data === false || $data === null) {
                    return null;
                }

                $decoded = json_decode($data, true);

                return is_array($decoded) ? $decoded : null;
            },
            'Failed to retrieve pending response'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function removePendingResponse(string $messageId): void
    {
        $this->executeRedisCommand(
            function () use ($messageId) {
                $key = $this->generatePendingResponseKey($messageId);
                $this->redis->del($key);

                $this->logger?->debug('Removed pending response', [
                    'messageId' => $messageId,
                    'key' => $key,
                ]);
            },
            'Failed to remove pending response'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function hasPendingResponse(string $messageId): bool
    {
        return $this->executeRedisCommand(
            function () use ($messageId) {
                $key = $this->generatePendingResponseKey($messageId);

                return $this->redis->exists($key) > 0;
            },
            'Failed to check pending response'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function cleanupOldPendingResponses(int $maxAge): int
    {
        return $this->executeRedisCommand(
            function () use ($maxAge) {
                $pattern = "$this->keyPrefix:pending_response:*";
                $keys = $this->redis->keys($pattern);
                $deletedCount = 0;

                foreach ($keys as $key) {
                    // Check if key has expired based on TTL
                    $ttl = $this->redis->ttl($key);
                    if ($ttl === -1 || $ttl > $this->messageTtl) {
                        // Key doesn't have TTL or TTL is too long, check creation time
                        // Since we can't get creation time from Redis directly,
                        // we'll rely on the TTL mechanism for cleanup
                        continue;
                    }

                    // For keys that are close to expiring, we can clean them up
                    if ($ttl < ($this->messageTtl - $maxAge)) {
                        $this->redis->del($key);
                        $deletedCount++;
                    }
                }

                $this->logger?->debug('Cleaned up old pending responses', [
                    'deletedCount' => $deletedCount,
                    'maxAge' => $maxAge,
                ]);

                return $deletedCount;
            },
            'Failed to cleanup old pending responses'
        );
    }

    /**
     * Logs an error message and throws an exception
     *
     * @param  string  $message  The error message to log
     * @param  Exception  $e  The original exception to be wrapped and thrown
     * @return never This method does not return as it always throws an exception
     *
     * @throws SseAdapterException
     */
    private function logAndThrow(string $message, Exception $e): never
    {
        $this->logger?->error($message);
        throw new SseAdapterException($message, 0, $e);
    }
}
