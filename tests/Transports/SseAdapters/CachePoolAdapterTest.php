<?php

namespace KLP\KlpMcpServer\Tests\Transports\SseAdapters;

use KLP\KlpMcpServer\Transports\SseAdapters\CachePoolAdapter;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

#[Small]
class CachePoolAdapterTest extends TestCase
{
    private CachePoolAdapter $adapter;
    private CacheItemPoolInterface $cacheMock;
    private LoggerInterface $loggerMock;
    private CacheItemInterface $cacheItemMock;

    protected function setUp(): void
    {
        $this->cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->cacheItemMock = $this->createMock(CacheItemInterface::class);

        $config = [
            'prefix' => 'test_prefix_',
            'ttl' => 50,
        ];

        $this->adapter = new CachePoolAdapter($config, $this->cacheMock, $this->loggerMock);
    }

    /**
     * Tests that a message is successfully stored in the cache when there are existing messages
     * for the client. Verifies that the new message is appended to the existing messages array
     * and that the correct TTL is set.
     */
    public function test_push_message_stores_message_in_cache(): void
    {
        $clientId = 'client123';
        $message = 'Hello, world!';
        $queueKey = 'test_prefix_|client|client123';
        $existingMessages = ['Previous message'];

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);
        $this->cacheItemMock
            ->method('get')
            ->willReturn($existingMessages);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with(array_merge($existingMessages, [$message]));

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that a message is properly stored in cache when there are no existing messages
     * for the client (cache miss). Verifies that a new array containing only the new message
     * is created and the correct TTL is set.
     */
    public function test_push_message_with_cache_miss(): void
    {
        $clientId = 'client456';
        $message = 'New message!';
        $queueKey = 'test_prefix_|client|client456';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with([$message]);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that the adapter properly handles InvalidArgumentException by logging an error
     * when attempting to push a message to the cache. Verifies that the error is logged
     * with the appropriate message.
     */
    public function test_push_message_handles_invalid_argument_exception(): void
    {
        $clientId = 'client789';
        $message = 'Error test message';
        $queueKey = 'test_prefix_|client|client789';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to add message to cache'));

        $this->adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that removeAllMessages successfully removes all messages from cache for the given client ID.
     */
    public function test_remove_all_messages_successfully_removes_messages(): void
    {
        $clientId = 'client321';
        $queueKey = 'test_prefix_|client|client321';

        $this->cacheMock
            ->expects($this->once())
            ->method('deleteItem')
            ->with($queueKey);

        $this->adapter->removeAllMessages($clientId);
    }

    /**
     * Tests that removeAllMessages logs an error when InvalidArgumentException is thrown.
     */
    public function test_remove_all_messages_handles_invalid_argument_exception(): void
    {
        $clientId = 'client654';
        $queueKey = 'test_prefix_|client|client654';

        $this->cacheMock
            ->method('deleteItem')
            ->with($queueKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to remove messages from cache'));

        $this->adapter->removeAllMessages($clientId);
    }

    /**
     * Tests that receiveMessages returns an array of messages when the cache contains messages
     * for the given client ID.
     */
    public function test_receive_messages_returns_array_of_messages(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123';
        $messages = ['Message 1', 'Message 2'];

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($messages);

        $result = $this->adapter->receiveMessages($clientId);

        $this->assertIsArray($result);
        $this->assertSame($messages, $result);
    }

    /**
     * Tests that receiveMessages returns an empty array when the cache does not contain messages
     * for the given client ID.
     */
    public function test_receive_messages_returns_empty_array_on_cache_miss(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(null);

        $result = $this->adapter->receiveMessages($clientId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Tests that popMessage retrieves and removes the first message when multiple messages exist in the queue.
     */
    public function test_pop_message_retrieves_and_removes_first_message(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123';
        $initialMessages = ['Message 1', 'Message 2', 'Message 3'];
        $remainingMessages = ['Message 2', 'Message 3'];

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($initialMessages);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($remainingMessages);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $result = $this->adapter->popMessage($clientId);

        $this->assertSame('Message 1', $result);
    }

    /**
     * Tests that popMessage retrieves and removes the only message in the queue.
     */
    public function test_pop_message_retrieves_and_removes_only_message(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456';
        $initialMessages = ['Solo message'];
        $remainingMessages = [];

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($initialMessages);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($remainingMessages);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $result = $this->adapter->popMessage($clientId);

        $this->assertSame('Solo message', $result);
    }

    /**
     * Tests that hasMessages returns true when messages exist in the cache.
     */
    public function test_has_messages_returns_true_when_messages_exist(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);

        $result = $this->adapter->hasMessages($clientId);

        $this->assertTrue($result);
    }

    /**
     * Tests that getMessageCount returns the correct count of messages
     * when messages exist in the cache.
     */
    public function test_get_message_count_returns_correct_count(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123';
        $messages = ['Message 1', 'Message 2', 'Message 3'];

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($messages);

        $result = $this->adapter->getMessageCount($clientId);

        $this->assertSame(3, $result);
    }

    /**
     * Tests that getMessageCount returns zero when there are no messages
     * in the cache (cache miss).
     */
    public function test_get_message_count_returns_zero_on_cache_miss(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(null);

        $result = $this->adapter->getMessageCount($clientId);

        $this->assertSame(0, $result);
    }

    /**
     * Tests that hasMessages returns false when there are no messages in the cache.
     */
    public function test_has_messages_returns_false_on_cache_miss(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $result = $this->adapter->hasMessages($clientId);

        $this->assertFalse($result);
    }

    /**
     * Tests that popMessage returns null when the cache has no messages for the given client.
     */
    public function test_pop_message_returns_null_on_cache_miss(): void
    {
        $clientId = 'client789';
        $queueKey = 'test_prefix_|client|client789';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(null);

        $result = $this->adapter->popMessage($clientId);

        $this->assertNull($result);
    }

    /**
     * Tests that popMessage logs an error and returns null when an InvalidArgumentException is thrown.
     */
    public function test_pop_message_handles_invalid_argument_exception(): void
    {
        $clientId = 'client654';
        $queueKey = 'test_prefix_|client|client654';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to pop message from cache'));

        $result = $this->adapter->popMessage($clientId);

        $this->assertNull($result);
    }

    /**
     * Tests that getLastPongResponseTimestamp retrieves the stored timestamp.
     */
    public function test_get_last_pong_response_timestamp_retrieves_value(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123|last_pong';
        $storedTimestamp = 1680000000;

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($storedTimestamp);

        $result = $this->adapter->getLastPongResponseTimestamp($clientId);

        $this->assertSame($storedTimestamp, $result);
    }

    /**
     * Tests that getLastPongResponseTimestamp returns null when not stored in the cache.
     */
    public function test_get_last_pong_response_timestamp_returns_null_when_not_stored(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456|last_pong';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(null);

        $result = $this->adapter->getLastPongResponseTimestamp($clientId);

        $this->assertNull($result);
    }

    /**
     * Tests that storeLastPongResponseTimestamp stores a specific timestamp in the cache.
     */
    public function test_store_last_pong_response_timestamp_with_specific_timestamp(): void
    {
        $clientId = 'client123';
        $timestamp = 1680000000;
        $queueKey = 'test_prefix_|client|client123|last_pong';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($timestamp);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->adapter->storeLastPongResponseTimestamp($clientId, $timestamp);
    }

    /**
     * Tests that storeLastPongResponseTimestamp stores the current timestamp when no timestamp is provided.
     */
    public function test_store_last_pong_response_timestamp_with_default_timestamp(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456|last_pong';
        $currentTimestamp = time();

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($this->greaterThanOrEqual($currentTimestamp));

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->adapter->storeLastPongResponseTimestamp($clientId);
    }
}
