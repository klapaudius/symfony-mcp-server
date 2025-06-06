<?php

namespace KLP\KlpMcpServer\Tests\Transports;

use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use KLP\KlpMcpServer\Transports\SseTransport;
use KLP\KlpMcpServer\Transports\SseTransportException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

#[Small]
class SseTransportTest extends TestCase
{
    private LoggerInterface|MockObject $loggerMock;

    private SseAdapterInterface|MockObject $adapterMock;

    private RouterInterface|MockObject $routerMock;

    private SseTransport $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->instance = new SseTransport($this->routerMock, $this->adapterMock, $this->loggerMock);
    }

    /**
     * Test that sending a string message calls the sendEvent method with 'message' event.
     */
    public function test_send_string_message(): void
    {
        // Arrange
        $message = 'Test string message';

        // Act
        ob_start();
        try {
            $this->instance->send($message);
            $output = ob_get_contents(); // Capture the output before clearing the buffer
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $this->assertEquals(
            'event: message'.PHP_EOL
            .'data: '.$message.PHP_EOL.PHP_EOL,
            $output
        );
    }

    /**
     * Test that initialize generates a unique client ID and sends an 'endpoint' event.
     */
    public function test_initialize_generates_client_id_and_sends_endpoint(): void
    {
        // Arrange
        $this->routerMock
            ->expects($this->once())
            ->method('generate')
            ->willReturnCallback(function (string $name, array $parameters = []): string {
                $this->assertEquals('message_route', $name);
                $this->assertArrayHasKey('sessionId', $parameters);

                return '/default-path/messages?sessionId='.$parameters['sessionId'];
            });
        // Act
        ob_start();
        try {
            $this->instance->initialize();
            $output = ob_get_contents(); // Capture the output before clearing the buffer
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $expectedClientId = $this->getProtectedProperty($this->instance, 'clientId');
        $expectedOutput = 'event: endpoint'.PHP_EOL
            .'data: /default-path/messages?sessionId='.$expectedClientId.PHP_EOL.PHP_EOL;

        $this->assertNotNull($expectedClientId);
        $this->assertEquals($expectedOutput, $output);
    }

    /**
     * Test that initialize does not overwrite an existing client ID.
     */
    public function test_initialize_does_not_overwrite_existing_client_id(): void
    {
        // Arrange
        $existingClientId = 'predefined-client-id';
        $this->setProtectedProperty($this->instance, 'clientId', $existingClientId);
        $this->routerMock
            ->expects($this->once())
            ->method('generate')
            ->with('message_route', ['sessionId' => $existingClientId])
            ->willReturn('/default-path/messages?sessionId='.$existingClientId);

        // Act
        ob_start();
        try {
            $this->instance->initialize();
            $output = ob_get_contents(); // Capture the output before clearing the buffer
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $currentClientId = $this->getProtectedProperty($this->instance, 'clientId');
        $expectedOutput = 'event: endpoint'.PHP_EOL
            .'data: /default-path/messages?sessionId='.$existingClientId.PHP_EOL.PHP_EOL;

        $this->assertSame($existingClientId, $currentClientId);
        $this->assertEquals($expectedOutput, $output);
    }

    /**
     * Test that sending an array message encodes it to JSON and calls sendEvent with 'message' event.
     */
    public function test_send_array_message(): void
    {
        // Define the input array and expected JSON
        // Arrange
        $messageArray = ['id' => 1234567890, 'key' => 'value', 'anotherKey' => 123];
        $messageJson = json_encode($messageArray);

        // Act
        ob_start();
        try {
            $this->instance->send($messageArray);
            $output = ob_get_contents(); // Capture the output before clearing the buffer
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $this->assertEquals(
            'event: message'.PHP_EOL
            .'data: '.$messageJson.PHP_EOL.PHP_EOL,
            $output
        );
    }

    /**
     * Test that sending an array message encodes it to JSON and calls sendEvent with 'message' event.
     */
    public function test_send_array_message_without_id_will_add_unique_id(): void
    {
        // Define the input array and expected JSON
        // Arrange
        $messageArray = ['key' => 'value', 'anotherKey' => 123];
        $messageJson = json_encode($messageArray);

        // Act
        ob_start();
        try {
            $this->instance->send($messageArray);
            $output = ob_get_contents(); // Capture the output before clearing the buffer
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $this->assertStringContainsString('data: {"id"', $output);
    }

    /**
     * Test that the start() method sets the connected flag to true and initializes the transport.
     */
    public function test_start_sets_connected_flag_and_initializes_transport(): void
    {
        // Act
        ob_start();
        try {
            $this->instance->start();
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $this->assertTrue($this->getProtectedProperty($this->instance, 'connected'));
    }

    /**
     * Test that the start() method does not reinitialize the transport if already connected.
     */
    public function test_start_does_not_reinitialize_transport_when_already_connected(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);

        // Act
        ob_start();
        try {
            $this->instance->start();
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $this->assertTrue($this->getProtectedProperty($this->instance, 'connected'));
    }

    /**
     * Test that the registered close handlers are executed when `close` is called.
     */
    public function test_close_executes_registered_handlers(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);
        $handlerExecuted = false;
        $this->instance->onClose(function () use (&$handlerExecuted) {
            $handlerExecuted = true;
        });

        // Act
        ob_start();
        try {
            $this->instance->close();
        } finally {
            ob_end_clean();
        }

        // Assert
        $this->assertTrue($handlerExecuted, 'The close handler was not executed.');
    }

    /**
     * Test that the adapter's resources are cleaned up when `close` is called with an adapter set.
     */
    public function test_close_removes_adapter_resources(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);
        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $adapterMock->expects($this->once())
            ->method('removeAllMessages')
            ->with('test-client-id');

        // Act
        ob_start();
        try {
            $this->instance->close();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Test that the `close` method sends a close event with the correct data.
     */
    public function test_close_sends_close_event(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);

        // Act
        ob_start();
        try {
            $this->instance->close();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        // Assert
        $this->assertEquals(
            'event: close'.PHP_EOL
            .'data: {"reason":"server_closed"}'.PHP_EOL.PHP_EOL,
            $output
        );
    }

    /**
     * Test that the `close` method does nothing if the connection is already closed.
     */
    public function test_close_does_nothing_when_already_closed(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', false);

        // Act
        ob_start();
        try {
            $this->instance->close();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        // Assert
        $this->assertEmpty($output, 'Output should be empty when already closed.');
    }

    /**
     * Test that exceptions during close handlers or adapter cleanup are logged correctly.
     */
    public function test_close_logs_exceptions_in_handlers_or_cleanup(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);
        $invocations = [
            'Error in SSE close handler: Handler Exception',
            'Error cleaning up SSE adapter resources on close: Adapter Exception',
        ];
        $this->loggerMock->expects($matcher = $this->exactly(2))
            ->method('error')
            ->with($this->callback(function ($arg) use (&$invocations, $matcher) {
                $this->assertEquals($arg, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));

        $this->instance->onClose(function () {
            throw new \Exception('Handler Exception');
        });

        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $adapterMock->expects($this->once())
            ->method('removeAllMessages')
            ->willThrowException(new SseAdapterException('Adapter Exception'));

        // Act
        ob_start();
        try {
            $this->instance->close();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Test that `receive` returns an empty array when no adapter is configured.
     */
    public function test_receive_returns_empty_array_without_adapter(): void
    {
        // Act
        $result = $this->instance->receive();

        // Assert
        $this->assertEquals([], $result, 'Expected receive to return an empty array when no adapter is set.');
    }

    /**
     * Test that `receive` logs an info message when no adapter is configured.
     */
    public function test_receive_logs_info_when_no_adapter(): void
    {
        // Arrange
        $this->instance->setAdapter(null);
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('SSE Transport::receive called but no adapter is configured.');

        // Act
        $this->instance->receive();
    }

    /**
     * Test that `receive` returns received messages when an adapter and client ID are set.
     */
    public function test_receive_returns_adapter_messages(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);

        $messages = [['type' => 'test', 'data' => 'value']];
        $adapterMock->expects($this->once())
            ->method('receiveMessages')
            ->with('test-client-id')
            ->willReturn($messages);

        // Act
        $result = $this->instance->receive();

        // Assert
        $this->assertEquals($messages, $result, 'Expected received messages to match the adapter output.');
    }

    /**
     * Test that `receive` calls the adapter's `receiveMessages` method with the correct client ID.
     */
    public function test_receive_calls_adapter_receive_messages_with_correct_client_id(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);

        $adapterMock->expects($this->once())
            ->method('receiveMessages')
            ->with('test-client-id');

        // Act
        $this->instance->receive();
    }

    /**
     * Test that `receive` triggers error handlers if the adapter throws an exception.
     */
    public function test_receive_triggers_error_handlers_on_adapter_exception(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'connected', true);
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);

        $adapterMock->expects($this->once())
            ->method('receiveMessages')
            ->willThrowException(new SseAdapterException('Adapter Exception'));

        $errorTriggered = false;
        $this->instance->onError(function (string $error) use (&$errorTriggered) {
            $errorTriggered = true;
            $this->assertStringContainsString('Adapter Exception', $error);
        });

        // Act
        $this->instance->receive();

        // Assert
        $this->assertTrue($errorTriggered, 'Error handlers were not triggered on adapter exception.');
    }

    /**
     * Test that `pushMessage` calls the adapter's `pushMessage` method with correct parameters.
     */
    public function test_push_message_calls_adapter_correctly(): void
    {
        // Arrange
        $clientId = 'test-client-id';
        $message = ['key' => 'value'];
        $expectedMessage = json_encode($message);

        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $adapterMock->expects($this->once())
            ->method('pushMessage')
            ->with($clientId, $expectedMessage);

        $this->instance->setAdapter($adapterMock);

        // Act
        $this->instance->pushMessage($clientId, $message);
    }

    /**
     * Test that `pushMessage` throws an exception if adapter is not set.
     */
    public function test_push_message_throws_exception_without_adapter(): void
    {
        // Expect exception
        $this->instance->setAdapter(null);
        $this->expectException(SseTransportException::class);
        $this->expectExceptionMessage('Cannot push message: SSE Adapter is not configured.');

        // Act
        $this->instance->pushMessage('client-id', ['key' => 'value']);
    }

    /**
     * Test that `pushMessage` throws an exception if JSON encoding fails.
     */
    public function test_push_message_throws_exception_on_json_error(): void
    {
        // Arrange
        $clientId = 'test-client-id';
        $invalidMessage = "\xB1\x31"; // Invalid UTF-8 sequence

        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);

        // Expect exception
        $this->expectException(SseTransportException::class);
        $this->expectExceptionMessage('Failed to JSON encode message for pushing');

        // Act
        $this->instance->pushMessage($clientId, [$invalidMessage]);
    }

    /**
     * Test that `processMessage` calls all registered message handlers with the correct parameters.
     */
    public function test_process_message_invokes_handlers(): void
    {
        // Arrange
        $messageHandlers = [];
        $this->instance->onMessage(function (string $clientId, array $message) use (&$messageHandlers) {
            $messageHandlers[] = ['clientId' => $clientId, 'message' => $message];
        });

        $clientId = 'test-client-id';
        $message = ['key' => 'value'];

        // Act
        $this->instance->processMessage($clientId, $message);

        // Assert
        $this->assertCount(1, $messageHandlers);
        $this->assertSame($clientId, $messageHandlers[0]['clientId']);
        $this->assertSame($message, $messageHandlers[0]['message']);
    }

    /**
     * Test that `triggerError` invokes all registered error handlers with the correct message.
     */
    public function test_trigger_error_invokes_registered_handlers(): void
    {
        // Arrange
        $errorTriggered = [];
        $this->instance->onError(function (string $error) use (&$errorTriggered) {
            $errorTriggered[] = $error;
        });
        $this->instance->onError(function (string $error) use (&$errorTriggered) {
            $errorTriggered[] = "Handler 2: $error";
        });

        $message = 'Test Error Message';

        // Act
        $this->invokeProtectedMethod($this->instance, 'triggerError', [$message]);

        // Assert
        $this->assertCount(2, $errorTriggered);
        $this->assertSame($message, $errorTriggered[0]);
        $this->assertSame("Handler 2: $message", $errorTriggered[1]);
    }

    /**
     * Test that `triggerError` logs exceptions thrown by error handlers.
     */
    public function test_trigger_error_logs_exceptions_in_handlers(): void
    {
        // Arrange
        $message = 'Error Message';
        $invocations = [
            'SSE Transport error: Error Message',
            'Error in SSE error handler itself: Test Exception',
        ];
        $this->loggerMock->expects($matcher = $this->exactly(2))
            ->method('error')
            ->with($this->callback(function ($arg) use (&$invocations, $matcher) {
                $this->assertEquals($arg, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));

        $this->instance->onError(function () {
            throw new \Exception('Test Exception');
        });

        $handlerCalled = false;
        $this->instance->onError(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Act
        $this->invokeProtectedMethod($this->instance, 'triggerError', [$message]);

        // Assert
        $this->assertTrue($handlerCalled, 'The second handler was not called after the exception.');
    }

    /**
     * Test that `processMessage` logs exceptions in message handlers and continues execution.
     */
    public function test_process_message_logs_exceptions_in_handlers(): void
    {
        // Arrange
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error processing SSE message via handler: Test Exception'));

        $this->instance->onMessage(function () {
            throw new \Exception('Test Exception');
        });

        $handlerCalled = false;
        $this->instance->onMessage(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $clientId = 'test-client-id';
        $message = ['key' => 'value'];

        // Act
        $this->instance->processMessage($clientId, $message);

        // Assert
        $this->assertTrue($handlerCalled, 'The second handler was not called after the exception.');
    }

    /**
     * Test that `processMessage` does nothing when no handlers are registered.
     */
    public function test_process_message_does_nothing_without_handlers(): void
    {
        // Arrange
        $clientId = 'test-client-id';
        $message = ['key' => 'value'];

        // Assert no exception is thrown or output generated
        $this->expectNotToPerformAssertions();

        // Act
        $this->instance->processMessage($clientId, $message);
    }

    private function invokeProtectedMethod(SseTransport $instance, string $string, array $array)
    {
        $reflection = new \ReflectionClass($instance);
        $method = $reflection->getMethod($string);
        $method->invokeArgs($instance, $array);
    }

    private function getProtectedProperty(SseTransport $instance, string $propertyName)
    {
        $reflection = new \ReflectionClass($instance);
        $prop = $reflection->getProperty($propertyName);

        return $prop->getValue($instance);
    }

    private function setProtectedProperty(SseTransport $instance, string $propertyName, string $propertyValue): void
    {
        $reflection = new \ReflectionClass($instance);
        $prop = $reflection->getProperty($propertyName);
        $prop->setValue($instance, $propertyValue);
    }

    /**
     * Test that `setPingInterval` updates `pingInterval` correctly for valid values.
     */
    public function test_set_ping_interval_updates_correctly(): void
    {
        // Arrange
        $validPingInterval = 10; // A valid interval in secondes
        $reflection = new \ReflectionClass($this->instance);
        $method = $reflection->getMethod('setPingInterval');

        // Act
        $method->invoke($this->instance, $validPingInterval);
        $updatedPingInterval = $reflection->getProperty('pingInterval')->getValue($this->instance);

        // Assert
        $this->assertEquals($validPingInterval, $updatedPingInterval);
    }

    /**
     * Test that `setPingInterval`set the ping interval to the minimum allowed value when the value is less than the minimum allowed value.
     */
    public function test_set_ping_interval_with_less_than_minimum_value(): void
    {
        // Arrange
        $invalidPingInterval = 1; // Less than the minimum allowed value (5s)
        $reflection = new \ReflectionClass($this->instance);
        $method = $reflection->getMethod('setPingInterval');

        // Act
        $method->invoke($this->instance, $invalidPingInterval);
        $updatedPingInterval = $reflection->getProperty('pingInterval')->getValue($this->instance);

        // Assert
        $this->assertEquals(5, $updatedPingInterval);
    }

    /**
     * Test that `setPingInterval`set the ping interval to the maximum allowed value when the value is more than the maximum allowed value.
     */
    public function test_set_ping_interval_with_more_than_maximum_value(): void
    {
        // Arrange
        $invalidPingInterval = 60; // More than the maximum allowed value (30 s)
        $reflection = new \ReflectionClass($this->instance);
        $method = $reflection->getMethod('setPingInterval');

        // Act
        $method->invoke($this->instance, $invalidPingInterval);
        $updatedPingInterval = $reflection->getProperty('pingInterval')->getValue($this->instance);

        // Assert
        $this->assertEquals(30, $updatedPingInterval);
    }

    /**
     * Test that `isConnected` returns true when the connection is active and conditions are met.
     */
    public function test_is_connected_returns_true_when_connected(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'lastPingTimestamp', time());
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $this->setProtectedProperty($this->instance, 'pingEnabled', true);

        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $adapterMock->expects($this->once())
            ->method('getLastPongResponseTimestamp')
            ->willReturn(time());
        $this->instance->setAdapter($adapterMock);

        // Act
        $result = $this->instance->isConnected();

        // Assert
        $this->assertTrue($result, 'Expected isConnected to return true when conditions are met.');
    }

    /**
     * Test that `getAdapter` returns the correct adapter instance when set.
     */
    public function test_get_adapter_returns_set_adapter(): void
    {
        // Arrange
        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->instance->setAdapter($adapterMock);

        // Act
        $result = $this->instance->getAdapter();

        // Assert
        $this->assertSame($adapterMock, $result, 'Expected getAdapter to return the set adapter instance.');
    }

    /**
     * Test that `getAdapter` returns null when no adapter is set.
     */
    public function test_get_adapter_returns_null_when_no_adapter_set(): void
    {
        // Arrange
        $this->instance->setAdapter(null);

        // Act
        $result = $this->instance->getAdapter();

        // Assert
        $this->assertNull($result, 'Expected getAdapter to return null when no adapter is set.');
    }

    /**
     * Test that `isConnected` returns false when the connection is inactive or conditions are not met.
     */
    public function test_is_connected_returns_false_when_not_connected(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'lastPingTimestamp', time());
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $this->setProtectedProperty($this->instance, 'pingEnabled', true);

        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $adapterMock->expects($this->once())
            ->method('getLastPongResponseTimestamp')
            ->willReturn(time() - 200);
        $this->instance->setAdapter($adapterMock);

        // Act
        $result = $this->instance->isConnected();

        // Assert
        $this->assertFalse($result, 'Expected isConnected to return false when not connected.');
    }

    public function test_is_connected_triggers_a_ping_message_if_needed(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'lastPingTimestamp', time() - 71);
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $this->setProtectedProperty($this->instance, 'pingEnabled', true);

        $adapterMock = $this->createMock(SseAdapterInterface::class);
        $adapterMock->expects($this->once())
            ->method('getLastPongResponseTimestamp')
            ->willReturn(time() - 70);
        $this->instance->setAdapter($adapterMock);

        // Act
        ob_start();
        try {
            $result = $this->instance->isConnected();
            $output = ob_get_contents(); // Capture the output before clearing the buffer
        } finally {
            ob_end_clean(); // Ensure the buffer is cleaned even if an exception occurs
        }

        // Assert
        $this->assertFalse($result, 'Expected isConnected to return false when connection is not active.');
        $this->assertStringContainsString('"method":"ping"', $output);
    }
}
