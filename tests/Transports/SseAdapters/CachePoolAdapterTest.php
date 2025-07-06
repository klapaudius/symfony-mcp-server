<?php

namespace KLP\KlpMcpServer\Tests\Transports\SseAdapters;

use KLP\KlpMcpServer\Transports\SseAdapters\CachePoolAdapter;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
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
        $this->cacheItemMock
            ->expects($matcher = $this->exactly(count($invocations)))
            ->method('set')
            ->with($this->callback(function ($value) use ($invocations, $matcher) {
                $this->assertEquals($value, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));
        $this->cacheMock
            ->expects($this->exactly(count($invocations)))
            ->method('save')
            ->with($this->cacheItemMock);

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

    /**
     * Tests that storeSamplingCapability stores true sampling capability in the cache.
     */
    public function test_store_sampling_capability_stores_true_value(): void
    {
        $clientId = 'client123';
        $hasSamplingCapability = true;
        $queueKey = 'test_prefix_|client|client123|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($hasSamplingCapability);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(86400);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests that storeSamplingCapability stores false sampling capability in the cache.
     */
    public function test_store_sampling_capability_stores_false_value(): void
    {
        $clientId = 'client456';
        $hasSamplingCapability = false;
        $queueKey = 'test_prefix_|client|client456|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($hasSamplingCapability);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(86400);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests that storeSamplingCapability handles InvalidArgumentException and throws SseAdapterException.
     */
    public function test_store_sampling_capability_handles_invalid_argument_exception(): void
    {
        $clientId = 'client789';
        $hasSamplingCapability = true;
        $queueKey = 'test_prefix_|client|client789|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to store sampling capability'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to store sampling capability');

        $this->adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests that hasSamplingCapability returns true when the client has sampling capability.
     */
    public function test_has_sampling_capability_returns_true_when_capability_exists(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
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
        $queueKey = 'test_prefix_|client|client456|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
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
        $queueKey = 'test_prefix_|client|client789|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
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
        $queueKey = 'test_prefix_|client|client123|sampling';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve sampling capability'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to retrieve sampling capability');

        $this->adapter->hasSamplingCapability($clientId);
    }

    /**
     * Tests that storePendingResponse stores response data with correct key and TTL.
     */
    public function test_store_pending_response_stores_data_successfully(): void
    {
        $messageId = 'msg123';
        $responseData = ['response' => 'test response', 'timestamp' => 1680000000];
        $expectedKey = 'test_prefix_|pending_response|msg123';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('set')
            ->with($responseData);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(50);

        $this->cacheMock
            ->expects($this->once())
            ->method('save')
            ->with($this->cacheItemMock);

        $this->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Stored pending response', [
                'messageId' => $messageId,
                'key' => $expectedKey,
            ]);

        $this->adapter->storePendingResponse($messageId, $responseData);
    }

    /**
     * Tests that storePendingResponse throws SseAdapterException on InvalidArgumentException.
     */
    public function test_store_pending_response_handles_invalid_argument_exception(): void
    {
        $messageId = 'msg456';
        $responseData = ['response' => 'test'];
        $expectedKey = 'test_prefix_|pending_response|msg456';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to store pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to store pending response');

        $this->adapter->storePendingResponse($messageId, $responseData);
    }

    /**
     * Tests that getPendingResponse retrieves stored response data.
     */
    public function test_get_pending_response_retrieves_stored_data(): void
    {
        $messageId = 'msg789';
        $expectedData = ['response' => 'stored response', 'timestamp' => 1680000000];
        $expectedKey = 'test_prefix_|pending_response|msg789';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
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
        $expectedKey = 'test_prefix_|pending_response|msg999';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
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
        $expectedKey = 'test_prefix_|pending_response|msg111';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
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
        $expectedKey = 'test_prefix_|pending_response|msg222';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to retrieve pending response');

        $this->adapter->getPendingResponse($messageId);
    }

    /**
     * Tests that removePendingResponse removes response data and logs debug message.
     */
    public function test_remove_pending_response_removes_data_successfully(): void
    {
        $messageId = 'msg333';
        $expectedKey = 'test_prefix_|pending_response|msg333';

        $this->cacheMock
            ->expects($this->once())
            ->method('deleteItem')
            ->with($expectedKey);

        $this->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Removed pending response', [
                'messageId' => $messageId,
                'key' => $expectedKey,
            ]);

        $this->adapter->removePendingResponse($messageId);
    }

    /**
     * Tests that removePendingResponse throws SseAdapterException on InvalidArgumentException.
     */
    public function test_remove_pending_response_handles_invalid_argument_exception(): void
    {
        $messageId = 'msg444';
        $expectedKey = 'test_prefix_|pending_response|msg444';

        $this->cacheMock
            ->method('deleteItem')
            ->with($expectedKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to remove pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to remove pending response');

        $this->adapter->removePendingResponse($messageId);
    }

    /**
     * Tests that hasPendingResponse returns true when response data exists.
     */
    public function test_has_pending_response_returns_true_when_data_exists(): void
    {
        $messageId = 'msg555';
        $expectedKey = 'test_prefix_|pending_response|msg555';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
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
        $expectedKey = 'test_prefix_|pending_response|msg666';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
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
        $expectedKey = 'test_prefix_|pending_response|msg777';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        $this->loggerMock
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to check pending response'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to check pending response');

        $this->adapter->hasPendingResponse($messageId);
    }

    /**
     * Tests that cleanupOldPendingResponses logs debug message and returns 0 (basic implementation).
     */
    public function test_cleanup_old_pending_responses_logs_and_returns_zero(): void
    {
        $maxAge = 3600;

        $this->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Cleanup requested', ['maxAge' => $maxAge]);

        $result = $this->adapter->cleanupOldPendingResponses($maxAge);

        $this->assertSame(0, $result);
    }

    /**
     * Tests constructor with default configuration values.
     */
    public function test_constructor_with_default_values(): void
    {
        $config = []; // Empty config to test defaults
        $adapter = new CachePoolAdapter($config, $this->cacheMock, $this->loggerMock);

        // Test that default prefix is used by checking generated key
        $clientId = 'test_client';
        $message = 'test message';
        $expectedKey = 'mcp_sse_|client|test_client'; // Default prefix

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $this->cacheItemMock
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
        $adapter = new CachePoolAdapter($config, $this->cacheMock, $this->loggerMock);

        $clientId = 'test_client';
        $message = 'test message';
        $expectedKey = 'custom_prefix_|client|test_client';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        $this->cacheItemMock
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(100); // Default TTL

        $adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests constructor with null logger.
     */
    public function test_constructor_with_null_logger(): void
    {
        $config = ['prefix' => 'test_', 'ttl' => 30];
        $adapter = new CachePoolAdapter($config, $this->cacheMock, null);

        // Test that adapter works without logger (should not throw exception)
        $clientId = 'test_client';
        $message = 'test message';
        $expectedKey = 'test_|client|test_client';

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        // Should not throw any exception
        $adapter->pushMessage($clientId, $message);

        // Test error handling without logger
        $this->cacheMock
            ->method('deleteItem')
            ->willThrowException($this->createMock(InvalidArgumentException::class));

        // Should not throw any exception even though logger is null
        $adapter->removeAllMessages($clientId);
    }

    /**
     * Tests hasMessages with empty array from cache.
     */
    public function test_has_messages_returns_false_for_empty_array(): void
    {
        $clientId = 'client123';
        $queueKey = 'test_prefix_|client|client123';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
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
        $queueKey = 'test_prefix_|client|client456';

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('get')
            ->willReturn([]); // Empty array

        $this->cacheItemMock
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

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Stored sampling capability', [
                'clientId' => $clientId,
                'key' => $queueKey,
                'hasSamplingCapability' => $hasSamplingCapability,
            ]);

        $this->adapter->storeSamplingCapability($clientId, $hasSamplingCapability);
    }

    /**
     * Tests debug logging in hasSamplingCapability method.
     */
    public function test_has_sampling_capability_logs_debug_information(): void
    {
        $clientId = 'client456';
        $queueKey = 'test_prefix_|client|client456|sampling';
        $expectedValue = true;

        $this->cacheMock
            ->method('getItem')
            ->with($queueKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(true);

        $this->cacheItemMock
            ->method('get')
            ->willReturn($expectedValue);

        $this->loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('Retrieved sampling capability', [
                'clientId' => $clientId,
                'key' => $queueKey,
                'isHit' => true,
                'value' => $expectedValue,
            ]);

        $result = $this->adapter->hasSamplingCapability($clientId);

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

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        // Should not throw exception
        $this->adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests edge case: Very long client ID.
     */
    public function test_very_long_client_id(): void
    {
        $clientId = str_repeat('a', 1000); // 1000 character client ID
        $message = 'test message';
        $expectedKey = "test_prefix_|client|$clientId";

        $this->cacheMock
            ->method('getItem')
            ->with($expectedKey)
            ->willReturn($this->cacheItemMock);

        $this->cacheItemMock
            ->method('isHit')
            ->willReturn(false);

        // Should not throw exception
        $this->adapter->pushMessage($clientId, $message);
    }

    /**
     * Tests edge case: Empty message string.
     */
    public function test_push_empty_message_string(): void
    {
        $clientId = 'client123';
        $message = ''; // Empty message
        $queueKey = 'test_prefix_|client|client123';

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
            ->with(['']); // Should accept empty string

        $this->adapter->pushMessage($clientId, $message);
    }
}
