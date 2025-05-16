<?php

namespace KLP\KlpMcpServer\Tests\Transports\SseAdapters;

use Exception;
use KLP\KlpMcpServer\Transports\SseAdapters\RedisAdapter;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Redis;

#[Small]
final class RedisAdapterTest extends TestCase
{
    private Redis|MockObject $redisMock;

    private LoggerInterface|MockObject $loggerMock;

    private RedisAdapter $redisAdapter;

    protected function setUp(): void
    {
        if (! class_exists(\Redis::class)) {
            eval(<<<'PHPUNIT_EVAL'
                class Redis {
                    const OPT_PREFIX = 2;
                    public function __call($name, $arguments) {}
                    public function connect($host, $port) {}
                    public function setOption($option, $value) {}
                    public function rpush($key, $value) {}
                    public function expire($key, $ttl) {}
                    public function lpop($key) {}
                    public function llen($key) {}
                    public function del($key) {}
                    public function set($key, $value) {}
                    public function get($key) {}
                    public function pexpire($key, $ttl) {}
                    public function pexpireat($key, $timestamp) {}
                    public function pttl($key) {}
                    public function psetex($key, $ttl, $value) {}
                }
            PHPUNIT_EVAL);
        }

        $this->redisMock = $this->createMock(Redis::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->redisAdapter = new RedisAdapter(
            $this->getMockRedisConfiguration(),
            $this->loggerMock,
            $this->redisMock
        );
    }

    private function getMockRedisConfiguration(): array
    {
        return ['connection' => 'localhost', 'prefix' => 'test_', 'ttl' => 120];
    }

    /**
     * Tests that the RedisAdapter constructor initializes correctly with valid parameters.
     */
    public function test_constructor_initializes_correctly(): void
    {
        $this->redisMock->expects($this->once())
            ->method('connect')
            ->with('localhost', 6379);

        $this->redisMock->expects($this->once())
            ->method('setOption')
            ->with(Redis::OPT_PREFIX, 'test_');

        $adapter = new RedisAdapter($this->getMockRedisConfiguration(), $this->loggerMock, $this->redisMock);

        $this->assertInstanceOf(RedisAdapter::class, $adapter);
    }

    /**
     * Tests that the RedisAdapter constructor throws an exception when Redis fails to connect.
     */
    public function test_constructor_throws_exception_on_failed_redis_connection(): void
    {
        $this->redisMock->expects($this->once())
            ->method('connect')
            ->willThrowException(new Exception('Connection error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to initialize Redis SSE Adapter'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to initialize Redis SSE Adapter');

        new RedisAdapter($this->getMockRedisConfiguration(), $this->loggerMock, $this->redisMock);
    }

    /**
     * Tests that the RedisAdapter constructor uses default values when configuration is partially missing.
     */
    public function test_constructor_uses_default_values_when_config_is_partial(): void
    {
        $config = ['connection' => 'localhost']; // Missing 'prefix' and 'ttl'

        $this->redisMock->expects($this->once())
            ->method('connect')
            ->with('localhost', 6379);

        $this->redisMock->expects($this->once())
            ->method('setOption')
            ->with(Redis::OPT_PREFIX, 'mcp_sse_'); // DEFAULT_PREFIX

        $adapter = new RedisAdapter($config, $this->loggerMock, $this->redisMock);

        $this->assertInstanceOf(RedisAdapter::class, $adapter);
    }

    /**
     * Tests that a message is successfully pushed to Redis.
     *
     * This method verifies that the `rpush` method is called with the correct key and message,
     * and that the `expire` method is invoked with the correct key and expiration time.
     */
    public function test_push_message_successfully_pushes_to_redis(): void
    {
        $clientId = 'client_123';
        $message = 'test message';
        $key = 'test_:client:client_123';

        $this->redisMock->expects($this->once())
            ->method('rpush')
            ->with($key, $message);

        $this->redisMock->expects($this->once())
            ->method('expire')
            ->with($key, 120);

        $this->redisAdapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that an exception is thrown when the Redis `rpush` operation fails while attempting
     * to push a message to the queue. Validates that the logger captures the error and the
     * appropriate exception with a specific message is thrown.
     */
    public function test_push_message_throws_exception_when_redis_fails(): void
    {
        $clientId = 'client_failure';
        $message = 'failure message';
        $key = 'test_:client:client_failure';

        $this->redisMock->expects($this->once())
            ->method('rpush')
            ->with($key, $message)
            ->willThrowException(new Exception('Redis error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to add message to Redis queue'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to add message to Redis queue');

        $this->redisAdapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that the pushMessage method correctly sets the expiration time for a message in Redis.
     */
    public function test_push_message_sets_expiration_correctly(): void
    {
        $clientId = 'client_456';
        $message = 'another test message';
        $key = 'test_:client:client_456';

        $this->redisMock->expects($this->once())
            ->method('rpush')
            ->with($key, $message);

        $this->redisMock->expects($this->once())
            ->method('expire')
            ->with($key, 120);

        $this->redisAdapter->pushMessage($clientId, $message);
    }

    /**
     * Tests that the removeAllMessages method successfully removes all messages associated with the given client in Redis.
     */
    public function test_remove_all_messages_successfully_removes_messages(): void
    {
        $clientId = 'client_789';
        $key = 'test_:client:client_789';

        $this->redisMock->expects($this->once())
            ->method('del')
            ->with($key);

        $this->redisAdapter->removeAllMessages($clientId);
    }

    /**
     * Tests that the removeAllMessages method throws an exception and logs an error when the Redis delete operation fails.
     */
    public function test_remove_all_messages_throws_exception_when_redis_fails(): void
    {
        $clientId = 'client_error';
        $key = 'test_:client:client_error';

        $this->redisMock->expects($this->once())
            ->method('del')
            ->with($key)
            ->willThrowException(new Exception('Redis delete error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to remove messages from Redis queue'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to remove messages from Redis queue');

        $this->redisAdapter->removeAllMessages($clientId);
    }

    /**
     * Tests that receiveMessages returns all messages and removes them from the Redis queue.
     */
    public function test_receive_messages_returns_all_messages(): void
    {
        $clientId = 'client_with_messages';
        $key = 'test_:client:client_with_messages';

        $this->redisMock->expects($this->exactly(4))
            ->method('lpop')
            ->with($key)
            ->willReturnOnConsecutiveCalls('message1', 'message2', 'message3', false);

        $messages = $this->redisAdapter->receiveMessages($clientId);

        $this->assertSame(['message1', 'message2', 'message3'], $messages);
    }

    /**
     * Tests that receiveMessages returns an empty array when the Redis queue is empty.
     */
    public function test_receive_messages_returns_empty_array_when_no_messages(): void
    {
        $clientId = 'client_empty';
        $key = 'test_:client:client_empty';

        $this->redisMock->expects($this->once())
            ->method('lpop')
            ->with($key)
            ->willReturn(false);

        $messages = $this->redisAdapter->receiveMessages($clientId);

        $this->assertSame([], $messages);
    }

    /**
     * Tests that receiveMessages throws an exception and logs an error when the Redis lpop operation fails.
     */
    public function test_receive_messages_throws_exception_on_failure(): void
    {
        $clientId = 'client_error';
        $key = 'test_:client:client_error';

        $this->redisMock->expects($this->once())
            ->method('lpop')
            ->with($key)
            ->willThrowException(new Exception('Redis lpop error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to receive messages from Redis queue'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to receive messages from Redis queue');

        $this->redisAdapter->receiveMessages($clientId);
    }

    /**
     * Tests that popMessage correctly retrieves and removes the oldest message from the Redis queue.
     */
    public function test_pop_message_returns_message_successfully(): void
    {
        $clientId = 'client_123';
        $key = 'test_:client:client_123';
        $expectedMessage = 'oldest message';

        $this->redisMock->expects($this->once())
            ->method('lpop')
            ->with($key)
            ->willReturn($expectedMessage);

        $actualMessage = $this->redisAdapter->popMessage($clientId);

        $this->assertSame($expectedMessage, $actualMessage);
    }

    /**
     * Tests that popMessage returns null when the Redis queue is empty.
     */
    public function test_pop_message_returns_null_when_queue_is_empty(): void
    {
        $clientId = 'client_empty';
        $key = 'test_:client:client_empty';

        $this->redisMock->expects($this->once())
            ->method('lpop')
            ->with($key)
            ->willReturn(false);

        $actualMessage = $this->redisAdapter->popMessage($clientId);

        $this->assertNull($actualMessage);
    }

    /**
     * Tests that popMessage throws an exception and logs an error when the Redis lpop operation fails.
     */
    public function test_pop_message_throws_exception_when_redis_fails(): void
    {
        $clientId = 'client_error';
        $key = 'test_:client:client_error';

        $this->redisMock->expects($this->once())
            ->method('lpop')
            ->with($key)
            ->willThrowException(new Exception('Redis pop error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to pop message from Redis queue'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to pop message from Redis queue');

        $this->redisAdapter->popMessage($clientId);
    }

    /**
     * Tests that hasMessages returns true when there are messages in Redis.
     */
    public function test_has_messages_returns_true_when_messages_exist(): void
    {
        $clientId = 'client_with_messages';
        $key = 'test_:client:client_with_messages';

        $this->redisMock->expects($this->once())
            ->method('llen')
            ->with($key)
            ->willReturn(3);

        $this->assertTrue($this->redisAdapter->hasMessages($clientId));
    }

    /**
     * Tests that hasMessages returns false when the Redis queue is empty.
     */
    public function test_has_messages_returns_false_when_queue_is_empty(): void
    {
        $clientId = 'client_no_messages';
        $key = 'test_:client:client_no_messages';

        $this->redisMock->expects($this->once())
            ->method('llen')
            ->with($key)
            ->willReturn(0);

        $this->assertFalse($this->redisAdapter->hasMessages($clientId));
    }

    /**
     * Tests that hasMessages handles Redis exceptions correctly.
     */
    public function test_has_messages_handles_redis_exception(): void
    {
        $clientId = 'client_exception';
        $key = 'test_:client:client_exception';

        $this->redisMock->expects($this->once())
            ->method('llen')
            ->with($key)
            ->willThrowException(new Exception('Redis error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to get message count'));

        $this->assertFalse($this->redisAdapter->hasMessages($clientId));
    }

    /**
     * Tests that getMessageCount returns the correct number of messages.
     */
    public function test_get_message_count_returns_correct_count(): void
    {
        $clientId = 'client_with_messages';
        $key = 'test_:client:client_with_messages';

        $this->redisMock->expects($this->once())
            ->method('llen')
            ->with($key)
            ->willReturn(5);

        $messageCount = $this->redisAdapter->getMessageCount($clientId);

        $this->assertSame(5, $messageCount);
    }

    /**
     * Tests that getMessageCount returns 0 if the key does not exist.
     */
    public function test_get_message_count_returns_zero_if_key_not_exist(): void
    {
        $clientId = 'client_no_key';
        $key = 'test_:client:client_no_key';

        $this->redisMock->expects($this->once())
            ->method('llen')
            ->with($key)
            ->willReturn(0);

        $messageCount = $this->redisAdapter->getMessageCount($clientId);

        $this->assertSame(0, $messageCount);
    }

    /**
     * Tests that getMessageCount logs an error and returns 0 if a Redis exception is thrown.
     */
    public function test_get_message_count_logs_error_and_returns_zero_on_exception(): void
    {
        $clientId = 'client_error';
        $key = 'test_:client:client_error';

        $this->redisMock->expects($this->once())
            ->method('llen')
            ->with($key)
            ->willThrowException(new Exception('Redis error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to get message count'));

        $messageCount = $this->redisAdapter->getMessageCount($clientId);

        $this->assertSame(0, $messageCount);
    }

    /**
     * Tests that storeLastPongResponseTimestamp sets the timestamp for the given client using the current time by default.
     */
    public function test_store_last_pong_response_uses_current_time_by_default(): void
    {
        $clientId = 'client_default_time';
        $key = 'test_:client:client_default_time:last_pong';
        $expectedTimestamp = time();

        $this->redisMock->expects($this->once())
            ->method('set')
            ->with($key, $this->logicalAnd($this->greaterThanOrEqual($expectedTimestamp), $this->lessThanOrEqual($expectedTimestamp + 2)));

        $this->redisMock->expects($this->once())
            ->method('expire')
            ->with($key, 120);

        $this->redisAdapter->storeLastPongResponseTimestamp($clientId);
    }

    /**
     * Tests that storeLastPongResponseTimestamp sets the timestamp for the given client using a custom timestamp.
     */
    public function test_store_last_pong_response_uses_custom_timestamp(): void
    {
        $clientId = 'client_with_custom_time';
        $key = 'test_:client:client_with_custom_time:last_pong';
        $customTimestamp = 1699999999;

        $this->redisMock->expects($this->once())
            ->method('set')
            ->with($key, $customTimestamp);

        $this->redisMock->expects($this->once())
            ->method('expire')
            ->with($key, 120);

        $this->redisAdapter->storeLastPongResponseTimestamp($clientId, $customTimestamp);
    }

    /**
     * Tests that storeLastPongResponseTimestamp throws an exception and logs an error when the Redis set operation fails.
     */
    public function test_store_last_pong_response_throws_exception_on_failure(): void
    {
        $clientId = 'client_error';
        $key = 'test_:client:client_error:last_pong';

        $this->redisMock->expects($this->once())
            ->method('set')
            ->with($key, $this->isType('int'))
            ->willThrowException(new Exception('Redis set error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to store last pong timestamp'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to store last pong timestamp');

        $this->redisAdapter->storeLastPongResponseTimestamp($clientId);
    }

    /**
     * Tests that getLastPongResponseTimestamp returns the correct timestamp when it exists in Redis.
     */
    public function test_get_last_pong_response_timestamp_returns_correct_timestamp(): void
    {
        $clientId = 'client_with_timestamp';
        $key = 'test_:client:client_with_timestamp:last_pong';
        $expectedTimestamp = 1699999999;

        $this->redisMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn((string) $expectedTimestamp);

        $timestamp = $this->redisAdapter->getLastPongResponseTimestamp($clientId);

        $this->assertSame($expectedTimestamp, $timestamp);
    }

    /**
     * Tests that getLastPongResponseTimestamp returns null when the key does not exist in Redis.
     */
    public function test_get_last_pong_response_timestamp_returns_null_if_key_not_exist(): void
    {
        $clientId = 'client_no_key';
        $key = 'test_:client:client_no_key:last_pong';

        $this->redisMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(false);

        $timestamp = $this->redisAdapter->getLastPongResponseTimestamp($clientId);

        $this->assertNull($timestamp);
    }

    /**
     * Tests that getLastPongResponseTimestamp throws an exception and logs an error when the Redis get operation fails.
     */
    public function test_get_last_pong_response_timestamp_throws_exception_on_failure(): void
    {
        $clientId = 'client_error';
        $key = 'test_:client:client_error:last_pong';

        $this->redisMock->expects($this->once())
            ->method('get')
            ->with($key)
            ->willThrowException(new Exception('Redis get error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to get last pong timestamp'));

        $this->expectException(SseAdapterException::class);
        $this->expectExceptionMessage('Failed to get last pong timestamp');

        $this->redisAdapter->getLastPongResponseTimestamp($clientId);
    }
}
