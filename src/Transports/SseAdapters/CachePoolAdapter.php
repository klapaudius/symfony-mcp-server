<?php

namespace KLP\KlpMcpServer\Transports\SseAdapters;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class CachePoolAdapter implements SseAdapterInterface
{
    private const DEFAULT_PREFIX = 'mcp_sse_';

    private const DEFAULT_MESSAGE_TTL = 100;

    private const SAMPLING_KEY_SUFFIX = '|sampling';

    /**
     * Key prefix for SSE messages
     */
    private string $keyPrefix;

    /**
     * Message expiration time in seconds
     */
    private int $messageTtl;

    public function __construct(
        private readonly array $config,
        private readonly CacheItemPoolInterface $cache,
        private readonly ?LoggerInterface $logger
    ) {
        $this->keyPrefix = $this->config['prefix'] ?? self::DEFAULT_PREFIX;
        $this->messageTtl = (int) ($this->config['ttl'] ?? self::DEFAULT_MESSAGE_TTL);
    }

    /**
     * Get the Redis key for a client's message queue
     *
     * @param  string  $clientId  The client ID
     * @return string The Redis key
     */
    private function generateQueueKey(string $clientId): string
    {
        return "$this->keyPrefix|client|$clientId";
    }

    /**
     * {@inheritDoc}
     */
    public function pushMessage(string $clientId, string $message): void
    {
        try {
            $cacheItem = $this->cache->getItem($this->generateQueueKey($clientId));

            $messages = $cacheItem->isHit() ? $cacheItem->get() : [];
            $messages[] = $message;
            $cacheItem->set($messages);
            $cacheItem->expiresAfter($this->messageTtl);
            $this->cache->save($cacheItem);
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to add message to cache: '.$e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function removeAllMessages(string $clientId): void
    {
        try {
            $this->cache->deleteItem($this->generateQueueKey($clientId));
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to remove messages from cache: '.$e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function receiveMessages(string $clientId): array
    {
        $messages = [];
        while ($message = $this->popMessage($clientId)) {
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function popMessage(string $clientId): ?string
    {
        try {
            $cacheItem = $this->cache->getItem($this->generateQueueKey($clientId));
            $messages = $cacheItem->get() ?? [];
            $message = array_shift($messages);
            $cacheItem->set($messages);
            $cacheItem->expiresAfter($this->messageTtl);
            $this->cache->save($cacheItem);

            return $message;
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to pop message from cache: '.$e->getMessage());

            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasMessages(string $clientId): bool
    {
        return $this->getMessageCount($clientId) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessageCount(string $clientId): int
    {
        return count($this->cache->getItem($this->generateQueueKey($clientId))->get() ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function storeLastPongResponseTimestamp(string $clientId, ?int $timestamp = null): void
    {
        $cacheItem = $this->cache->getItem($this->generateQueueKey($clientId).'|last_pong');
        $cacheItem->set($timestamp ?? time());
        $cacheItem->expiresAfter($this->messageTtl);
        $this->cache->save($cacheItem);
    }

    /**
     * {@inheritDoc}
     */
    public function getLastPongResponseTimestamp(string $clientId): ?int
    {
        return $this->cache->getItem($this->generateQueueKey($clientId).'|last_pong')->get();
    }

    /**
     * {@inheritDoc}
     */
    public function storeSamplingCapability(string $clientId, bool $hasSamplingCapability): void
    {
        try {
            $key = $this->generateQueueKey($clientId).self::SAMPLING_KEY_SUFFIX;
            $cacheItem = $this->cache->getItem($key);
            $cacheItem->set($hasSamplingCapability);
            $cacheItem->expiresAfter(60 * 60 * 24);
            $this->cache->save($cacheItem);

            $this->logger?->debug('Stored sampling capability', [
                'clientId' => $clientId,
                'key' => $key,
                'hasSamplingCapability' => $hasSamplingCapability,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to store sampling capability: '.$e->getMessage());
            throw new SseAdapterException('Failed to store sampling capability', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasSamplingCapability(string $clientId): bool
    {
        try {
            $key = $this->generateQueueKey($clientId).self::SAMPLING_KEY_SUFFIX;
            $cacheItem = $this->cache->getItem($key);
            $isHit = $cacheItem->isHit();
            $value = $cacheItem->get();

            $this->logger?->debug('Retrieved sampling capability', [
                'clientId' => $clientId,
                'key' => $key,
                'isHit' => $isHit,
                'value' => $value,
            ]);

            return $value ?? false;
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to retrieve sampling capability: '.$e->getMessage());
            throw new SseAdapterException('Failed to retrieve sampling capability', 0, $e);
        }
    }

    /**
     * Get the cache key for a pending response
     *
     * @param  string  $messageId  The message ID
     * @return string The cache key
     */
    private function generatePendingResponseKey(string $messageId): string
    {
        return "$this->keyPrefix|pending_response|$messageId";
    }

    /**
     * {@inheritDoc}
     */
    public function storePendingResponse(string $messageId, array $responseData): void
    {
        try {
            $key = $this->generatePendingResponseKey($messageId);
            $cacheItem = $this->cache->getItem($key);
            $cacheItem->set($responseData);
            $cacheItem->expiresAfter($this->messageTtl);
            $this->cache->save($cacheItem);

            $this->logger?->debug('Stored pending response', [
                'messageId' => $messageId,
                'key' => $key,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to store pending response: '.$e->getMessage());
            throw new SseAdapterException('Failed to store pending response', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingResponse(string $messageId): ?array
    {
        try {
            $key = $this->generatePendingResponseKey($messageId);
            $cacheItem = $this->cache->getItem($key);

            if (! $cacheItem->isHit()) {
                return null;
            }

            $data = $cacheItem->get();

            return is_array($data) ? $data : null;
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to retrieve pending response: '.$e->getMessage());
            throw new SseAdapterException('Failed to retrieve pending response', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function removePendingResponse(string $messageId): void
    {
        try {
            $key = $this->generatePendingResponseKey($messageId);
            $this->cache->deleteItem($key);

            $this->logger?->debug('Removed pending response', [
                'messageId' => $messageId,
                'key' => $key,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to remove pending response: '.$e->getMessage());
            throw new SseAdapterException('Failed to remove pending response', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasPendingResponse(string $messageId): bool
    {
        try {
            $key = $this->generatePendingResponseKey($messageId);
            $cacheItem = $this->cache->getItem($key);

            return $cacheItem->isHit();
        } catch (InvalidArgumentException $e) {
            $this->logger?->error('Failed to check pending response: '.$e->getMessage());
            throw new SseAdapterException('Failed to check pending response', 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function cleanupOldPendingResponses(int $maxAge): int
    {
        // Note: This is a basic implementation that doesn't iterate over keys
        // For a production implementation with many pending responses,
        // consider using a separate tracking mechanism or Redis-specific commands
        $this->logger?->debug('Cleanup requested', ['maxAge' => $maxAge]);

        // Cache pools don't typically provide key iteration,
        // so we rely on the TTL mechanism for cleanup
        return 0;
    }
}
