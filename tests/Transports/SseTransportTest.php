<?php

namespace KLP\KlpMcpServer\Tests\Transports;

use KLP\KlpMcpServer\Transports\SseAdapters\SseAdapterInterface;
use KLP\KlpMcpServer\Transports\SseTransport;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Small]
class SseTransportTest extends TestCase
{
    private LoggerInterface|MockObject $loggerMock;

    private SseTransport $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->instance = new SseTransport('/default-path', $this->loggerMock);
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
            .'data: /default-path/message?sessionId='.$expectedClientId.PHP_EOL.PHP_EOL;

        $this->assertNotNull($expectedClientId);
        $this->assertEquals($expectedOutput, $output);
    }

    /**
     * Test that initialize does not overwrite an existing client ID.
     */
    public function test_initialize_d_does_not_overwrite_existing_client_id(): void
    {
        // Arrange
        $existingClientId = 'predefined-client-id';
        $this->setProtectedProperty($this->instance, 'clientId', $existingClientId);

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
            .'data: /default-path/message?sessionId='.$existingClientId.PHP_EOL.PHP_EOL;

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
        $this->assertEquals(
            'event: message'.PHP_EOL
            .'data: '.$messageJson.PHP_EOL.PHP_EOL,
            $output
        );
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
            ->willThrowException(new \Exception('Adapter Exception'));

        // Act
        ob_start();
        try {
            $this->instance->close();
        } finally {
            ob_end_clean();
        }
    }
}
