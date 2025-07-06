<?php

namespace KLP\KlpMcpServer\Tests\Services\SamplingService;

use KLP\KlpMcpServer\Services\SamplingService\ResponseWaiter;
use KLP\KlpMcpServer\Services\SamplingService\SamplingClient;
use KLP\KlpMcpServer\Services\SamplingService\SamplingResponseHandler;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Small]
class SamplingResponseHandlerTest extends TestCase
{
    private SamplingResponseHandler $handler;

    private SamplingClient|MockObject $mockSamplingClient;

    private LoggerInterface|MockObject $mockLogger;

    private ResponseWaiter|MockObject $mockResponseWaiter;

    protected function setUp(): void
    {
        $this->mockSamplingClient = $this->createMock(SamplingClient::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockResponseWaiter = $this->createMock(ResponseWaiter::class);

        $this->handler = new SamplingResponseHandler(
            $this->mockSamplingClient,
            $this->mockLogger
        );
    }

    /**
     * Tests that execute method handles successful result properly with logging.
     */
    public function test_execute_with_successful_result(): void
    {
        $clientId = 'client123';
        $messageId = 'msg456';
        $result = ['status' => 'success', 'data' => 'response data'];

        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'result' => $result,
        ];

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => true,
                'hasError' => false,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId, $result);
    }

    /**
     * Tests that execute method handles error response properly with logging.
     */
    public function test_execute_with_error_response(): void
    {
        $clientId = 'client789';
        $messageId = 'msg999';
        $error = ['code' => -32603, 'message' => 'Internal error'];

        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'error' => $error,
        ];

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => false,
                'hasError' => true,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId, null, $error);
    }

    /**
     * Tests that execute method handles both result and error (error takes precedence).
     */
    public function test_execute_with_both_result_and_error(): void
    {
        $clientId = 'client111';
        $messageId = 'msg222';
        $result = ['data' => 'some data'];
        $error = ['code' => -32602, 'message' => 'Invalid params'];

        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'error' => $error, // Error should take precedence
        ];

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => true,
                'hasError' => true,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId, $result, $error);
    }

    /**
     * Tests that execute method handles neither result nor error (null values).
     */
    public function test_execute_with_neither_result_nor_error(): void
    {
        $clientId = 'client333';
        $messageId = 'msg444';

        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'result' => null,
        ];

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => false,
                'hasError' => false,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId);
    }

    /**
     * Tests that execute method handles exception from getResponseWaiter and logs error.
     */
    public function test_execute_handles_exception_from_get_response_waiter(): void
    {
        $clientId = 'client555';
        $messageId = 'msg666';
        $result = ['data' => 'test'];
        $exception = new \RuntimeException('Failed to get response waiter');

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => true,
                'hasError' => false,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willThrowException($exception);

        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to handle sampling response', [
                'error' => 'Failed to get response waiter',
                'messageId' => $messageId,
            ]);

        // Should not throw exception
        $this->handler->execute($clientId, $messageId, $result);
    }

    /**
     * Tests that execute method handles exception from handleResponse and logs error.
     */
    public function test_execute_handles_exception_from_handle_response(): void
    {
        $clientId = 'client777';
        $messageId = 'msg888';
        $result = ['data' => 'test'];
        $exception = new \InvalidArgumentException('Invalid response format');

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => true,
                'hasError' => false,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->willThrowException($exception);

        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to handle sampling response', [
                'error' => 'Invalid response format',
                'messageId' => $messageId,
            ]);

        // Should not throw exception
        $this->handler->execute($clientId, $messageId, $result);
    }

    /**
     * Tests that isHandle returns true when ResponseWaiter is waiting for the message ID.
     */
    public function test_is_handle_returns_true_when_waiting_for_message(): void
    {
        $messageId = 'msg123';

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('isWaitingFor')
            ->with($messageId)
            ->willReturn(true);

        $result = $this->handler->isHandle($messageId);

        $this->assertTrue($result);
    }

    /**
     * Tests that isHandle returns false when ResponseWaiter is not waiting for the message ID.
     */
    public function test_is_handle_returns_false_when_not_waiting_for_message(): void
    {
        $messageId = 'msg456';

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('isWaitingFor')
            ->with($messageId)
            ->willReturn(false);

        $result = $this->handler->isHandle($messageId);

        $this->assertFalse($result);
    }

    /**
     * Tests that isHandle returns false when exception occurs getting ResponseWaiter.
     */
    public function test_is_handle_returns_false_on_exception_from_get_response_waiter(): void
    {
        $messageId = 'msg789';
        $exception = new \RuntimeException('Transport not available');

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willThrowException($exception);

        $result = $this->handler->isHandle($messageId);

        $this->assertFalse($result);
    }

    /**
     * Tests that isHandle returns false when exception occurs in isWaitingFor.
     */
    public function test_is_handle_returns_false_on_exception_from_is_waiting_for(): void
    {
        $messageId = 'msg999';
        $exception = new \InvalidArgumentException('Invalid message ID format');

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('isWaitingFor')
            ->with($messageId)
            ->willThrowException($exception);

        $result = $this->handler->isHandle($messageId);

        $this->assertFalse($result);
    }

    /**
     * Tests that execute works with integer message IDs.
     */
    public function test_execute_with_integer_message_id(): void
    {
        $clientId = 'client123';
        $messageId = 12345;
        $result = ['status' => 'ok'];

        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'result' => $result,
        ];

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with('SamplingResponseHandler::execute', [
                'clientId' => $clientId,
                'messageId' => $messageId,
                'hasResult' => true,
                'hasError' => false,
            ]);

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId, $result);
    }

    /**
     * Tests that isHandle works with integer message IDs.
     */
    public function test_is_handle_with_integer_message_id(): void
    {
        $messageId = 54321;

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('isWaitingFor')
            ->with($messageId)
            ->willReturn(true);

        $result = $this->handler->isHandle($messageId);

        $this->assertTrue($result);
    }

    /**
     * Tests execute with empty arrays for result and error.
     */
    public function test_execute_with_empty_arrays(): void
    {
        $clientId = 'client_empty';
        $messageId = 'msg_empty';
        $emptyResult = [];
        $emptyError = [];

        // When both result and error are provided, error takes precedence
        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'error' => $emptyError,
        ];

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId, $emptyResult, $emptyError);
    }

    /**
     * Tests execute with complex nested array data.
     */
    public function test_execute_with_complex_data_structures(): void
    {
        $clientId = 'client_complex';
        $messageId = 'msg_complex';
        $complexResult = [
            'nested' => [
                'deep' => [
                    'data' => ['array', 'of', 'values'],
                    'numeric' => 42,
                    'boolean' => true,
                    'null_value' => null,
                ],
            ],
            'metadata' => [
                'timestamp' => 1680000000,
                'version' => '1.0.0',
            ],
        ];

        $expectedMessage = [
            'id' => $messageId,
            'jsonrpc' => '2.0',
            'result' => $complexResult,
        ];

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('handleResponse')
            ->with($expectedMessage);

        $this->handler->execute($clientId, $messageId, $complexResult);
    }

    /**
     * Tests execute with special characters in client ID and message ID.
     */
    public function test_execute_with_special_characters(): void
    {
        $clientId = 'client@#$%^&*()_+{}|:<>?[]\\;\'\".,/~`';
        $messageId = 'msg@#$%^&*()_+{}|:<>?[]\\;\'\".,/~`';
        $result = ['special' => 'characters test'];

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        // Should handle special characters without issues
        $this->handler->execute($clientId, $messageId, $result);
    }

    /**
     * Tests execute with very long strings.
     */
    public function test_execute_with_long_strings(): void
    {
        $clientId = str_repeat('a', 1000);
        $messageId = str_repeat('b', 1000);
        $result = ['long_data' => str_repeat('c', 10000)];

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        // Should handle long strings without issues
        $this->handler->execute($clientId, $messageId, $result);
    }

    /**
     * Tests isHandle with string representation of zero.
     */
    public function test_is_handle_with_string_zero(): void
    {
        $messageId = '0';

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('isWaitingFor')
            ->with($messageId)
            ->willReturn(false);

        $result = $this->handler->isHandle($messageId);

        $this->assertFalse($result);
    }

    /**
     * Tests isHandle with integer zero.
     */
    public function test_is_handle_with_integer_zero(): void
    {
        $messageId = 0;

        $this->mockSamplingClient
            ->expects($this->once())
            ->method('getResponseWaiter')
            ->willReturn($this->mockResponseWaiter);

        $this->mockResponseWaiter
            ->expects($this->once())
            ->method('isWaitingFor')
            ->with($messageId)
            ->willReturn(true);

        $result = $this->handler->isHandle($messageId);

        $this->assertTrue($result);
    }
}