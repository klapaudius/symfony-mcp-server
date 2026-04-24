<?php

namespace KLP\KlpMcpServer\Tests\Transports\SseAdapters;

use KLP\KlpMcpServer\Transports\SseAdapters\CachePoolAdapter;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

#[Small]
class CachePoolAdapterTest extends TestCase
{
    private CachePoolAdapter $adapter;

    private CacheItemPoolInterface&Stub $cacheMock;

    private LoggerInterface&Stub $loggerMock;

    private CacheItemInterface&Stub $cacheItemMock;

    protected function setUp(): void
    {
        $this->cacheMock = $this->createStub(CacheItemPoolInterface::class);
        $this->loggerMock = $this->createStub(LoggerInterface::class);
        $this->cacheItemMock = $this->createStub(CacheItemInterface::class);

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

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('isHit')
            ->willReturn(true);
        $cacheItemMock
            ->method('get')
            ->willReturn($existingMessages);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with(array_merge($existingMessages, [$message]));

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $adapter->pushMessage($clientId, $message);
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

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with([$message]);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $adapter->pushMessage($clientId, $message);
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

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to add message to cache'));

        $adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that removeAllMessages successfully removes all messages from cache for the given client ID.
     */
    public function test_remove_all_messages_successfully_removes_messages(): void
    {
        $clientId = 'client321';
        $queueKey = 'test_prefix_|client|client321';

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->expects($this->once())
            ->method('deleteItem')
            ->with($queueKey);

        $adapter->removeAllMessages($clientId);
    }

    /**
     * Tests that removeAllMessages logs an error when InvalidArgumentException is thrown.
     */
    public function test_remove_all_messages_handles_invalid_argument_exception(): void
    {
        $clientId = 'client654';

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('deleteItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to remove messages from cache'));

        $adapter->removeAllMessages($clientId);
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

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->expects($this->exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                ['Message 1', 'Message 2'],
                ['Message 2'],
                []
            );
        $invocations = [
            ['Message 2'],
            [],
            [],
        ];
        $cacheItemMock
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('set')
            ->with($this->callback(function ($value) use ($invocations, $matcher) {
                $this->assertEquals($value, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));
        $cacheMock
            ->expects($this->exactly(count($invocations)))
            ->method('save')
            ->with($cacheItemMock);

        $result = $adapter->receiveMessages($clientId);

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

        $this->cacheMock
            ->method('getItem')
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

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('get')
            ->willReturn($initialMessages);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($remainingMessages);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $result = $adapter->popMessage($clientId);

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

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('get')
            ->willReturn($initialMessages);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($remainingMessages);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $result = $adapter->popMessage($clientId);

        $this->assertSame('Solo message', $result);
    }

    /**
     * Tests that hasMessages returns true when messages exist in the cache.
     */
    public function test_has_messages_returns_true_when_messages_exist(): void
    {
        $clientId = 'client123';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(['message']);

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
        $messages = ['Message 1', 'Message 2', 'Message 3'];

        $this->cacheMock
            ->method('getItem')
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

        $this->cacheMock
            ->method('getItem')
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

        $this->cacheMock
            ->method('getItem')
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

        $this->cacheMock
            ->method('getItem')
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

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to pop message from cache'));

        $result = $adapter->popMessage($clientId);

        $this->assertNull($result);
    }

    /**
     * Tests that getLastPongResponseTimestamp retrieves the stored timestamp.
     */
    public function test_get_last_pong_response_timestamp_retrieves_value(): void
    {
        $clientId = 'client123';
        $storedTimestamp = 1680000000;

        $this->cacheMock
            ->method('getItem')
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

        $this->cacheMock
            ->method('getItem')
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

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($timestamp);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $adapter->storeLastPongResponseTimestamp($clientId, $timestamp);
    }

    /**
     * Tests that storeLastPongResponseTimestamp stores the current timestamp when no timestamp is provided.
     */
    public function test_store_last_pong_response_timestamp_with_default_timestamp(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456|last_pong';
        $currentTimestamp = time();

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($this->greaterThanOrEqual($currentTimestamp));

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $adapter->storeLastPongResponseTimestamp($clientId);
    }

    /**
     * Tests that storeSamplingCapability stores true sampling capability in the cache.
     */
    public function test_store_sampling_capability_stores_true_value(): void
    {
        $clientId = 'client123';
        $hasSamplingCapability = true;
        $queueKey = 'test_prefix_|client|client123|sampling';

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($hasSamplingCapability);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(86400);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests that storeSamplingCapability stores false sampling capability in the cache.
     */
    public function test_store_sampling_capability_stores_false_value(): void
    {
        $clientId = 'client456';
        $hasSamplingCapability = false;
        $queueKey = 'test_prefix_|client|client456|sampling';

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($hasSamplingCapability);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(86400);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests that storeSamplingCapability handles InvalidArgumentException and throws SseAdapterException.
     */
    public function test_store_sampling_capability_handles_invalid_argument_exception(): void
    {
        $clientId = 'client789';
        $hasSamplingCapability = true;

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to store sampling capability'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to store sampling capability');

        $adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests that hasSamplingCapability returns true when the client has sampling capability.
     */
    public function test_has_sampling_capability_returns_true_when_capability_exists(): void
    {
        $clientId = 'client123';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(true);

        $result = $this->adapter->hasSamplingCapability($clientId);

        $this->assertTrue($result);
    }

    /**
     * Tests that hasSamplingCapability returns false when the client has no sampling capability stored.
     */
    public function test_has_sampling_capability_returns_false_when_capability_is_false(): void
    {
        $clientId = 'client456';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(false);

        $result = $this->adapter->hasSamplingCapability($clientId);

        $this->assertFalse($result);
    }

    /**
     * Tests that hasSamplingCapability returns false when no sampling capability is stored (cache miss).
     */
    public function test_has_sampling_capability_returns_false_on_cache_miss(): void
    {
        $clientId = 'client789';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn(null);

        $result = $this->adapter->hasSamplingCapability($clientId);

        $this->assertFalse($result);
    }

    /**
     * Tests that hasSamplingCapability handles InvalidArgumentException and throws SseAdapterException.
     */
    public function test_has_sampling_capability_handles_invalid_argument_exception(): void
    {
        $clientId = 'client123';

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve sampling capability'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to retrieve sampling capability');

        $adapter->hasSamplingCapability($clientId);
    }

    /**
     * Tests that storePendingResponse stores response data with correct key and TTL.
     */
    public function test_store_pending_response_stores_data_successfully(): void
    {
        $messageId = 'msg123';
        $responseData = ['response' => 'test response', 'timestamp' => 1680000000];
        $expectedKey = 'test_prefix_|pending_response|msg123';

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $loggerMock);

        $cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($responseData);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($cacheItemMock);

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Stored pending response', [
                'messageId' => $messageId,
                'key' => $expectedKey,
            ]);

        $adapter->storePendingResponse($messageId, $responseData);
    }

    /**
     * Tests that storePendingResponse throws SseAdapterException on InvalidArgumentException.
     */
    public function test_store_pending_response_handles_invalid_argument_exception(): void
    {
        $messageId = 'msg456';
        $responseData = ['response' => 'test'];

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to store pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to store pending response');

        $adapter->storePendingResponse($messageId, $responseData);
    }

    /**
     * Tests that getPendingResponse retrieves stored response data.
     */
    public function test_get_pending_response_retrieves_stored_data(): void
    {
        $messageId = 'msg789';
        $expectedData = ['response' => 'stored response', 'timestamp' => 1680000000];

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($expectedData);

        $result = $this->adapter->getPendingResponse($messageId);

        $this->assertSame($expectedData, $result);
    }

    /**
     * Tests that getPendingResponse returns null when no data is found.
     */
    public function test_get_pending_response_returns_null_on_cache_miss(): void
    {
        $messageId = 'msg999';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $result = $this->adapter->getPendingResponse($messageId);

        $this->assertNull($result);
    }

    /**
     * Tests that getPendingResponse returns null when stored data is not an array.
     */
    public function test_get_pending_response_returns_null_for_non_array_data(): void
    {
        $messageId = 'msg111';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);

        $this->cacheItemMock
            ->method('get')
            ->willReturn('not_an_array');

        $result = $this->adapter->getPendingResponse($messageId);

        $this->assertNull($result);
    }

    /**
     * Tests that getPendingResponse throws SseAdapterException on InvalidArgumentException.
     */
    public function test_get_pending_response_handles_invalid_argument_exception(): void
    {
        $messageId = 'msg222';

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to retrieve pending response');

        $adapter->getPendingResponse($messageId);
    }

    /**
     * Tests that removePendingResponse removes response data and logs debug message.
     */
    public function test_remove_pending_response_removes_data_successfully(): void
    {
        $messageId = 'msg333';
        $expectedKey = 'test_prefix_|pending_response|msg333';

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $loggerMock);

        $cacheMock
            ->expects($this->once())
            ->method('deleteItem')
            ->with($expectedKey);

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Removed pending response', [
                'messageId' => $messageId,
                'key' => $expectedKey,
            ]);

        $adapter->removePendingResponse($messageId);
    }

    /**
     * Tests that removePendingResponse throws SseAdapterException on InvalidArgumentException.
     */
    public function test_remove_pending_response_handles_invalid_argument_exception(): void
    {
        $messageId = 'msg444';

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('deleteItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to remove pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to remove pending response');

        $adapter->removePendingResponse($messageId);
    }

    /**
     * Tests that hasPendingResponse returns true when response data exists.
     */
    public function test_has_pending_response_returns_true_when_data_exists(): void
    {
        $messageId = 'msg555';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);

        $result = $this->adapter->hasPendingResponse($messageId);

        $this->assertTrue($result);
    }

    /**
     * Tests that hasPendingResponse returns false when no response data exists.
     */
    public function test_has_pending_response_returns_false_when_no_data_exists(): void
    {
        $messageId = 'msg666';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $result = $this->adapter->hasPendingResponse($messageId);

        $this->assertFalse($result);
    }

    /**
     * Tests that hasPendingResponse throws SseAdapterException on InvalidArgumentException.
     */
    public function test_has_pending_response_handles_invalid_argument_exception(): void
    {
        $messageId = 'msg777';

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        $loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to check pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to check pending response');

        $adapter->hasPendingResponse($messageId);
    }

    /**
     * Tests that cleanupOldPendingResponses logs debug message and returns 0 (basic implementation).
     */
    public function test_cleanup_old_pending_responses_logs_and_returns_zero(): void
    {
        $maxAge = 3600;

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Cleanup requested', ['maxAge' => $maxAge]);

        $result = $adapter->cleanupOldPendingResponses($maxAge);

        $this->assertSame(0, $result);
    }

    /**
     * Tests constructor with default configuration values.
     */
    public function test_constructor_with_default_values(): void
    {
        $config = []; // Empty config to test defaults
        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter($config, $cacheMock, $this->loggerMock);

        // Test that default prefix is used by checking generated key
        $clientId = 'test_client';
        $message = 'test message';
        $expectedKey = 'mcp_sse_|client|test_client'; // Default prefix

        $cacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(100); // Default TTL

        $adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests constructor with partial configuration (only prefix).
     */
    public function test_constructor_with_partial_config(): void
    {
        $config = ['prefix' => 'custom_prefix_']; // Only prefix, TTL should use default
        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $cacheItemMock = $this->createMock(CacheItemInterface::class);
        $adapter = new CachePoolAdapter($config, $cacheMock, $this->loggerMock);

        $clientId = 'test_client';
        $message = 'test message';
        $expectedKey = 'custom_prefix_|client|test_client';

        $cacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(100); // Default TTL

        $adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that the adapter works without a logger and does not throw any exception,
     * including when a cache operation fails (error is silently swallowed).
     */
    public function test_constructor_with_null_logger_must_not_raise_any_exception(): void
    {
        $this->expectNotToPerformAssertions();

        $config = ['prefix' => 'test_', 'ttl' => 30];
        $adapter = new CachePoolAdapter($config, $this->cacheMock, null);

        // Test that adapter works without logger (should not throw exception)
        $clientId = 'test_client';
        $message = 'test message';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        // Should not throw any exception
        $adapter->pushMessage($clientId, $message);

        // Test error handling without logger
        $this->cacheMock
            ->method('deleteItem')
            ->willThrowException($this->createStub(InvalidArgumentException::class));

        // Should not throw any exception even though logger is null
        $adapter->removeAllMessages($clientId);
    }

    /**
     * Tests hasMessages with empty array from cache.
     */
    public function test_has_messages_returns_false_for_empty_array(): void
    {
        $clientId = 'client123';

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn([]); // Empty array

        $result = $this->adapter->hasMessages($clientId);

        $this->assertFalse($result);
    }

    /**
     * Tests popMessage returns null when messages array is empty.
     */
    public function test_pop_message_returns_null_when_messages_array_is_empty(): void
    {
        $clientId = 'client456';

        $cacheItemMock = $this->createMock(CacheItemInterface::class);

        $this->cacheMock
            ->method('getItem')
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('get')
            ->willReturn([]); // Empty array

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with([]);

        $result = $this->adapter->popMessage($clientId);

        $this->assertNull($result);
    }

    /**
     * Tests debug logging in storeSamplingCapability method.
     */
    public function test_store_sampling_capability_logs_debug_information(): void
    {
        $clientId = 'client123';
        $hasSamplingCapability = true;
        $queueKey = 'test_prefix_|client|client123|sampling';

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Stored sampling capability', [
                'clientId' => $clientId,
                'key' => $queueKey,
                'hasSamplingCapability' => $hasSamplingCapability,
            ]);

        $adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests debug logging in hasSamplingCapability method.
     */
    public function test_has_sampling_capability_logs_debug_information(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456|sampling';
        $expectedValue = true;

        $loggerMock = $this->createMock(LoggerInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $this->cacheMock, $loggerMock);

        $this->cacheMock
            ->method('getItem')
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($expectedValue);

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Retrieved sampling capability', [
                'clientId' => $clientId,
                'key' => $queueKey,
                'isHit' => true,
                'value' => $expectedValue,
            ]);

        $result = $adapter->hasSamplingCapability($clientId);

        $this->assertTrue($result);
    }

    /**
     * Tests edge case: Special characters in client ID.
     */
    public function test_special_characters_in_client_id(): void
    {
        $clientId = 'client@#$%^&*()_+{}|:<>?[]\;".,/~`';
        $message = 'test message';
        $expectedKey = "test_prefix_|client|$clientId";

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        // Should not throw exception
        $adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests edge case: Very long client ID.
     */
    public function test_very_long_client_id(): void
    {
        $clientId = str_repeat('a', 1000); // 1000 character client ID
        $message = 'test message';
        $expectedKey = "test_prefix_|client|$clientId";

        $cacheMock = $this->createMock(CacheItemPoolInterface::class);
        $adapter = new CachePoolAdapter(['prefix' => 'test_prefix_', 'ttl' => 50], $cacheMock, $this->loggerMock);

        $cacheMock
            ->expects($this->once())
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        // Should not throw exception
        $adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests edge case: Empty message string.
     */
    public function test_push_empty_message_string(): void
    {
        $clientId = 'client123';
        $message = ''; // Empty message

        $cacheItemMock = $this->createMock(CacheItemInterface::class);

        $this->cacheMock
            ->method('getItem')
            ->willReturn($cacheItemMock);

        $cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with(['']); // Should accept empty string

        $this->adapter->pushMessage($clientId, $message);
    }
}
