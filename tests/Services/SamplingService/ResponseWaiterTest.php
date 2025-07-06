<?php

declare(strict_types=1);

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ResponseWaiterTest extends TestCase
{
    private ResponseWaiter $responseWaiter;
    private LoggerInterface $logger;
    private SseAdapterInterface $adapter;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->adapter = $this->createMock(SseAdapterInterface::class);
    }

    public function test_constructor_with_defaults(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        // Verify it doesn't throw and creates instance
        $this->assertInstanceOf(ResponseWaiter::class, $responseWaiter);
    }

    public function test_constructor_with_adapter_and_timeout(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter, 60);
        
        // Verify it doesn't throw and creates instance
        $this->assertInstanceOf(ResponseWaiter::class, $responseWaiter);
    }

    public function test_wait_for_response_timeout(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, null, 1); // 1 second timeout
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Waiting for response', [
                'messageId' => 'test-123',
                'timeout' => 1,
            ]);
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Response timeout', [
                'messageId' => 'test-123',
                'timeout' => 1,
            ]);

        $this->expectException(JsonRpcErrorException::class);
        $this->expectExceptionMessage('Sampling request timed out after 1 seconds');
        
        $responseWaiter->waitForResponse('test-123', 1);
    }

    public function test_wait_for_response_immediate_success(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, null, 1); // No adapter, 1 second timeout
        
        // Test the checkForResponse private method directly with pre-populated data
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => ['data' => 'success'], 'timestamp' => time()]
        ]);

        // Test checkForResponse method directly
        $checkMethod = $reflection->getMethod('checkForResponse');
        $checkMethod->setAccessible(true);
        $result = $checkMethod->invoke($responseWaiter, 'test-123');
        
        $this->assertEquals(['data' => 'success'], $result);
    }

    public function test_wait_for_response_with_adapter_storage(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('storePendingResponse')
            ->with('test-123', $this->logicalAnd(
                $this->arrayHasKey('response'),
                $this->arrayHasKey('timestamp')
            ));

        $this->adapter->expects($this->atLeastOnce())
            ->method('getPendingResponse')
            ->with('test-123')
            ->willReturn(null);

        $this->adapter->expects($this->once())
            ->method('removePendingResponse')
            ->with('test-123');

        $this->expectException(JsonRpcErrorException::class);
        $responseWaiter->waitForResponse('test-123', 1);
    }

    public function test_wait_for_response_with_adapter_storage_failure(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('storePendingResponse')
            ->willThrowException(new \RuntimeException('Storage failed'));

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with($this->stringContains('Failed to store pending response'), $this->anything());

        $this->expectException(JsonRpcErrorException::class);
        $responseWaiter->waitForResponse('test-123', 1);
    }

    public function test_handle_response_with_result(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Handling response', ['messageId' => 'test-123']);

        // First, register a pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'result' => ['data' => 'success']
        ]);

        // Verify response was stored
        $pending = $pendingProperty->getValue($responseWaiter);
        $this->assertEquals(['data' => 'success'], $pending['test-123']['response']);
    }

    public function test_handle_response_with_error(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        // First, register a pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'error' => [
                'message' => 'Test error',
                'code' => -32603
            ]
        ]);

        // Verify error was stored as exception
        $pending = $pendingProperty->getValue($responseWaiter);
        $this->assertInstanceOf(JsonRpcErrorException::class, $pending['test-123']['response']);
        $this->assertEquals('Test error', $pending['test-123']['response']->getMessage());
    }

    public function test_handle_response_with_error_missing_fields(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        // First, register a pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'error' => []  // Missing message and code
        ]);

        // Verify error was stored with defaults
        $pending = $pendingProperty->getValue($responseWaiter);
        $this->assertInstanceOf(JsonRpcErrorException::class, $pending['test-123']['response']);
        $this->assertEquals('Unknown error', $pending['test-123']['response']->getMessage());
    }

    public function test_handle_response_ignores_invalid_message(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $this->logger->expects($this->never())
            ->method('debug');

        // Message without ID
        $responseWaiter->handleResponse(['result' => 'data']);
        
        // Message with non-string ID
        $responseWaiter->handleResponse(['id' => 123, 'result' => 'data']);
    }

    public function test_handle_response_ignores_unexpected_message(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $this->logger->expects($this->never())
            ->method('debug');

        // Message for ID we're not waiting for
        $responseWaiter->handleResponse(['id' => 'unknown-id', 'result' => 'data']);
    }

    public function test_handle_response_with_callback(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $callbackCalled = false;
        $callbackResult = null;
        
        $responseWaiter->registerCallback('test-123', function ($response) use (&$callbackCalled, &$callbackResult) {
            $callbackCalled = true;
            $callbackResult = $response;
        });

        // Register pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'result' => ['data' => 'success']
        ]);

        $this->assertTrue($callbackCalled);
        $this->assertEquals(['data' => 'success'], $callbackResult);
    }

    public function test_handle_response_with_callback_exception(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $responseWaiter->registerCallback('test-123', function ($response) {
            throw new \RuntimeException('Callback failed');
        });

        // Register pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Response callback error', [
                'messageId' => 'test-123',
                'error' => 'Callback failed'
            ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'result' => ['data' => 'success']
        ]);
    }

    public function test_handle_response_with_adapter(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $storedData = ['response' => null, 'timestamp' => time()];
        
        $this->adapter->expects($this->once())
            ->method('getPendingResponse')
            ->with('test-123')
            ->willReturn($storedData);

        $this->adapter->expects($this->once())
            ->method('storePendingResponse')
            ->with('test-123', $this->callback(function ($data) {
                return isset($data['response']) && $data['response'] === ['data' => 'success'];
            }));

        // Register pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'result' => ['data' => 'success']
        ]);
    }

    public function test_handle_response_with_adapter_failure(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('getPendingResponse')
            ->willThrowException(new \RuntimeException('Adapter failed'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Failed to update response in adapter'), $this->anything());

        // Register pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $responseWaiter->handleResponse([
            'id' => 'test-123',
            'result' => ['data' => 'success']
        ]);
    }

    public function test_cleanup_removes_old_responses(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, null, 30);
        
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        
        $callbacksProperty = $reflection->getProperty('responseCallbacks');
        $callbacksProperty->setAccessible(true);
        
        // Add old and new responses
        $now = time();
        $pendingProperty->setValue($responseWaiter, [
            'old-1' => ['response' => null, 'timestamp' => $now - 100], // Very old
            'old-2' => ['response' => null, 'timestamp' => $now - 65],  // Just over threshold
            'new-1' => ['response' => null, 'timestamp' => $now - 10],  // Recent
        ]);
        
        $callbacksProperty->setValue($responseWaiter, [
            'old-1' => function() {},
            'old-2' => function() {},
            'new-1' => function() {},
        ]);

        $this->logger->expects($this->exactly(2))
            ->method('warning')
            ->with($this->stringContains('Cleaned up stale response'), $this->anything());

        $responseWaiter->cleanup();
        
        $pending = $pendingProperty->getValue($responseWaiter);
        $callbacks = $callbacksProperty->getValue($responseWaiter);
        
        $this->assertArrayNotHasKey('old-1', $pending);
        $this->assertArrayNotHasKey('old-2', $pending);
        $this->assertArrayHasKey('new-1', $pending);
        
        $this->assertArrayNotHasKey('old-1', $callbacks);
        $this->assertArrayNotHasKey('old-2', $callbacks);
        $this->assertArrayHasKey('new-1', $callbacks);
    }

    public function test_is_waiting_for_in_memory(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        $this->assertTrue($responseWaiter->isWaitingFor('test-123'));
        $this->assertFalse($responseWaiter->isWaitingFor('unknown-id'));
    }

    public function test_is_waiting_for_with_adapter(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('hasPendingResponse')
            ->with('test-123')
            ->willReturn(true);

        $this->assertTrue($responseWaiter->isWaitingFor('test-123'));
    }

    public function test_is_waiting_for_with_adapter_failure(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('hasPendingResponse')
            ->willThrowException(new \RuntimeException('Adapter failed'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Failed to check adapter for pending response'), $this->anything());

        $this->assertFalse($responseWaiter->isWaitingFor('test-123'));
    }

    public function test_is_waiting_for_with_int_message_id(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('hasPendingResponse')
            ->with('123')
            ->willReturn(true);

        $this->assertTrue($responseWaiter->isWaitingFor(123));
    }

    public function test_check_for_response_from_adapter(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $storedData = [
            'response' => ['data' => 'from-adapter'],
            'timestamp' => time()
        ];
        
        $this->adapter->expects($this->once())
            ->method('getPendingResponse')
            ->with('test-123')
            ->willReturn($storedData);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($responseWaiter);
        $method = $reflection->getMethod('checkForResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($responseWaiter, 'test-123');
        
        $this->assertEquals(['data' => 'from-adapter'], $result);
    }

    public function test_wait_for_response_with_adapter_finds_response(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        // First call returns null (not ready), second call returns response
        $this->adapter->expects($this->exactly(2))
            ->method('getPendingResponse')
            ->with('test-123')
            ->willReturnOnConsecutiveCalls(
                null,
                ['response' => ['data' => 'from-adapter'], 'timestamp' => time()]
            );
        
        $this->adapter->expects($this->once())
            ->method('storePendingResponse');
        
        $this->adapter->expects($this->once())
            ->method('removePendingResponse')
            ->with('test-123');
        
        $result = $responseWaiter->waitForResponse('test-123', 2);
        $this->assertEquals(['data' => 'from-adapter'], $result);
    }

    public function test_wait_for_response_removes_from_adapter_on_success(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter, 1); // 1 second timeout
        
        $this->adapter->expects($this->once())
            ->method('storePendingResponse');
        
        $this->adapter->expects($this->once())
            ->method('removePendingResponse')
            ->with('test-123');
        
        // Use adapter to simulate immediate response availability on first check
        $this->adapter->expects($this->once())
            ->method('getPendingResponse')
            ->with('test-123')
            ->willReturn(['response' => ['data' => 'success'], 'timestamp' => time()]);
        
        $result = $responseWaiter->waitForResponse('test-123');
        $this->assertEquals(['data' => 'success'], $result);
    }

    public function test_wait_for_response_removes_from_adapter_on_timeout(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter, 1);
        
        $this->adapter->expects($this->once())
            ->method('removePendingResponse')
            ->with('test-123');
        
        try {
            $responseWaiter->waitForResponse('test-123');
        } catch (JsonRpcErrorException $e) {
            // Expected
        }
    }

    public function test_wait_for_response_logs_adapter_remove_failure(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter, 1);
        
        $this->adapter->expects($this->once())
            ->method('removePendingResponse')
            ->willThrowException(new \RuntimeException('Remove failed'));
        
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Failed to remove timed out response from adapter'), $this->anything());
        
        try {
            $responseWaiter->waitForResponse('test-123');
        } catch (JsonRpcErrorException $e) {
            // Expected
        }
    }

    public function test_handle_response_with_null_result(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        // Register pending request
        $reflection = new \ReflectionClass($responseWaiter);
        $pendingProperty = $reflection->getProperty('pendingResponses');
        $pendingProperty->setAccessible(true);
        $pendingProperty->setValue($responseWaiter, [
            'test-123' => ['response' => null, 'timestamp' => time()]
        ]);

        // Response without result field
        $responseWaiter->handleResponse([
            'id' => 'test-123'
        ]);

        // Verify null was stored
        $pending = $pendingProperty->getValue($responseWaiter);
        $this->assertNull($pending['test-123']['response']);
    }

    public function test_register_callback(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger);
        
        $callback = function($response) {};
        $responseWaiter->registerCallback('test-123', $callback);
        
        // Use reflection to verify callback was registered
        $reflection = new \ReflectionClass($responseWaiter);
        $callbacksProperty = $reflection->getProperty('responseCallbacks');
        $callbacksProperty->setAccessible(true);
        $callbacks = $callbacksProperty->getValue($responseWaiter);
        
        $this->assertArrayHasKey('test-123', $callbacks);
        $this->assertSame($callback, $callbacks['test-123']);
    }

    public function test_check_for_response_with_adapter_exception(): void
    {
        $responseWaiter = new ResponseWaiter($this->logger, $this->adapter);
        
        $this->adapter->expects($this->once())
            ->method('getPendingResponse')
            ->willThrowException(new \RuntimeException('Adapter error'));
        
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Failed to check adapter for response'), $this->anything());
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($responseWaiter);
        $method = $reflection->getMethod('checkForResponse');
        $method->setAccessible(true);
        
        $result = $method->invoke($responseWaiter, 'test-123');
        $this->assertNull($result);
    }
}