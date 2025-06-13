<?php

namespace KLP\KlpMcpServer\Tests\Server\Request;

use KLP\KlpMcpServer\Server\Request\PingHandler;
use KLP\KlpMcpServer\Transports\SseTransportInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class PingHandlerTest extends TestCase
{
    private SseTransportInterface $transportMock;

    private PingHandler $pingHandler;

    protected function setUp(): void
    {
        $this->transportMock = $this->createMock(SseTransportInterface::class);
        $this->pingHandler = new PingHandler($this->transportMock);
    }

    /**
     * Tests that the execute method sends the correct response and returns the expected result.
     *
     * Specifically, verifies that the transport system receives the correct response structure
     * (including message ID, JSON-RPC version, and result) and asserts that the method
     * returns an empty array as the result.
     */
    public function test_execute_sends_correct_response(): void
    {
        $messageId = '12345';
        $method = 'ping';
        $clientId = 'test-client';
        $params = null;

        // Expect the transport to send the correct response
        $expectedResponse = ['id' => $messageId, 'jsonrpc' => '2.0', 'result' => []];
        $this->transportMock->expects($this->once())
            ->method('send')
            ->with($expectedResponse);

        $result = $this->pingHandler->execute($method, $clientId, $messageId, $params);

        // Assert that the method returns an empty array
        $this->assertSame([], $result);
    }

    /**
     * Tests that isHandle returns true when the method is "ping".
     */
    public function test_is_handle_returns_true_for_ping(): void
    {
        $method = 'ping';

        $result = $this->pingHandler->isHandle($method);

        // Assert that isHandle returns true for "ping"
        $this->assertTrue($result);
    }

    /**
     * Tests that isHandle returns false when the method is not "ping".
     */
    public function test_is_handle_returns_false_for_other_method(): void
    {
        $method = 'other/method';

        $result = $this->pingHandler->isHandle($method);

        // Assert that isHandle returns false for non-"ping"
        $this->assertFalse($result);
    }
}
