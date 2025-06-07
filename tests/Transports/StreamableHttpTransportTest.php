<?php

namespace KLP\KlpMcpServer\Tests\Transports;

use KLP\KlpMcpServer\Transports\Exception\StreamableHttpTransportException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterException;
use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use KLP\KlpMcpServer\Transports\StreamableHttpTransport;
use KLP\KlpMcpServer\Transports\StreamableHttpTransportInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

#[Small]
class StreamableHttpTransportTest extends TestCase
{
    private LoggerInterface|MockObject $loggerMock;

    private SseAdapterInterface|MockObject $adapterMock;

    private RouterInterface|MockObject $routerMock;

    private StreamableHttpTransport $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->adapterMock = $this->createMock(SseAdapterInterface::class);
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->instance = new StreamableHttpTransport($this->routerMock, $this->adapterMock, $this->loggerMock);
    }

    /**
     * Test that isConnected returns true when the adapter has messages and the connection is active.
     */
    public function test_is_connected_returns_true_when_connected(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $this->setProtectedProperty($this->instance, 'connected', true);
        $this->setProtectedProperty($this->instance, 'lastPingTimestamp', time());

        $this->adapterMock->expects($this->once())
            ->method('hasMessages')
            ->with('test-client-id')
            ->willReturn(true);

        // Act
        $result = $this->instance->isConnected();

        // Assert
        $this->assertTrue($result, 'Expected isConnected to return true when conditions are met.');
    }

    /**
     * Test that isConnected returns false when the adapter has no messages.
     */
    public function test_is_connected_returns_false_when_no_messages(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $this->setProtectedProperty($this->instance, 'connected', true);
        $this->setProtectedProperty($this->instance, 'lastPingTimestamp', time());

        $this->adapterMock->expects($this->once())
            ->method('hasMessages')
            ->with('test-client-id')
            ->willReturn(false);

        // Act
        $result = $this->instance->isConnected();

        // Assert
        $this->assertFalse($result, 'Expected isConnected to return false when adapter has no messages.');
    }

    /**
     * Test that isConnected logs debug information.
     */
    public function test_is_connected_logs_debug_information(): void
    {
        // Arrange
        $this->setProtectedProperty($this->instance, 'clientId', 'test-client-id');
        $this->setProtectedProperty($this->instance, 'connected', true);
        $this->setProtectedProperty($this->instance, 'lastPingTimestamp', time());

        $this->adapterMock->expects($this->once())
            ->method('hasMessages')
            ->willReturn(true);

        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('Streamable HTTP Transport::isConnected: hasMessages: true');

        // Act
        $this->instance->isConnected();
    }

    /**
     * Test that setConnected updates the connected flag and lastPingTimestamp.
     */
    public function test_set_connected_updates_state(): void
    {
        // Arrange
        $currentTime = time();

        // Act
        $this->instance->setConnected(true);

        // Assert
        $this->assertTrue($this->getProtectedProperty($this->instance, 'connected'));
        $this->assertGreaterThanOrEqual($currentTime, $this->getProtectedProperty($this->instance, 'lastPingTimestamp'));
    }

    /**
     * Test that processMessage calls all registered message handlers with the correct parameters.
     */
    public function test_process_message_invokes_handlers(): void
    {
        // Arrange
        $messageHandlers = [];
        $this->invokeProtectedMethod($this->instance, 'onMessage', [function (string $clientId, array $message) use (&$messageHandlers) {
            $messageHandlers[] = ['clientId' => $clientId, 'message' => $message];
        }]);

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
     * Test that processMessage logs exceptions in message handlers and continues execution.
     */
    public function test_process_message_logs_exceptions_in_handlers(): void
    {
        // Arrange
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error processing Streamable HTTP message via handler:'));

        $this->invokeProtectedMethod($this->instance, 'onMessage', [function () {
            throw new \Exception('Test Exception');
        }]);

        $handlerCalled = false;
        $this->invokeProtectedMethod($this->instance, 'onMessage', [function () use (&$handlerCalled) {
            $handlerCalled = true;
        }]);

        $clientId = 'test-client-id';
        $message = ['key' => 'value'];

        // Act
        $this->instance->processMessage($clientId, $message);

        // Assert
        $this->assertTrue($handlerCalled, 'The second handler was not called after the exception.');
    }

    /**
     * Test that pushMessage calls the adapter's pushMessage method with correct parameters.
     */
    public function test_push_message_calls_adapter_correctly(): void
    {
        // Arrange
        $clientId = 'test-client-id';
        $message = ['key' => 'value'];
        $expectedMessage = json_encode($message);

        $this->adapterMock->expects($this->once())
            ->method('pushMessage')
            ->with($clientId, $expectedMessage);

        // Act
        $this->instance->pushMessage($clientId, $message);
    }

    /**
     * Test that pushMessage throws an exception if adapter is not set.
     */
    public function test_push_message_throws_exception_without_adapter(): void
    {
        // Arrange
        $this->instance->setAdapter(null);

        // Expect exception
        $this->expectException(StreamableHttpTransportException::class);
        $this->expectExceptionMessage('Cannot push message: Adapter is not configured.');

        // Act
        $this->instance->pushMessage('client-id', ['key' => 'value']);
    }

    /**
     * Test that pushMessage throws an exception if JSON encoding fails.
     */
    public function test_push_message_throws_exception_on_json_error(): void
    {
        // Arrange
        $clientId = 'test-client-id';
        $invalidMessage = ["\xB1\x31"]; // Invalid UTF-8 sequence

        // Expect exception
        $this->expectException(StreamableHttpTransportException::class);
        $this->expectExceptionMessage('Failed to JSON encode message for pushing');

        // Act
        $this->instance->pushMessage($clientId, $invalidMessage);
    }

    /**
     * Test that getTransportName returns the correct name.
     */
    public function test_get_transport_name_returns_correct_value(): void
    {
        // Act
        $result = $this->invokeProtectedMethod($this->instance, 'getTransportName', []);

        // Assert
        $this->assertEquals('Streamable HTTP Transport', $result);
    }

    /**
     * Helper method to invoke protected methods.
     */
    private function invokeProtectedMethod(StreamableHttpTransport $instance, string $methodName, array $parameters)
    {
        $reflection = new \ReflectionClass($instance);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($instance, $parameters);
    }

    /**
     * Helper method to get protected property values.
     */
    private function getProtectedProperty(StreamableHttpTransport $instance, string $propertyName)
    {
        $reflection = new \ReflectionClass($instance);
        $property = $reflection->getProperty($propertyName);

        return $property->getValue($instance);
    }

    /**
     * Helper method to set protected property values.
     */
    private function setProtectedProperty(StreamableHttpTransport $instance, string $propertyName, $propertyValue): void
    {
        $reflection = new \ReflectionClass($instance);
        $property = $reflection->getProperty($propertyName);
        $property->setValue($instance, $propertyValue);
    }
}
