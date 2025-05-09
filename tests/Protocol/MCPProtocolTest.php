<?php

namespace KLP\KlpMcpServer\Tests\Protocol;

use KLP\KlpMcpServer\Protocol\MCPProtocol;
use KLP\KlpMcpServer\Transports\TransportInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Small]
class MCPProtocolTest extends TestCase
{
    private TransportInterface|MockObject $mockTransport;

    private MCPProtocol $mcpProtocol;

    protected function setUp(): void
    {
        $this->mockTransport = $this->createMock(TransportInterface::class);
        $this->mcpProtocol = new MCPProtocol($this->mockTransport);
    }

    /**
     * Test that connect method starts the transport
     */
    public function test_connect_starts_transport(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('start');

        $this->mockTransport
            ->expects($this->once())
            ->method('isConnected')
            ->willReturn(false);

        $this->mockTransport
            ->expects($this->once())
            ->method('close');

        $this->mcpProtocol->connect();
    }

    /**
     * Test connect processes messages when transport is connected
     */
    public function test_connect_processes_messages(): void
    {
        $messages = ['message1', 'message2'];

        $this->mockTransport
            ->expects($this->once())
            ->method('start');

        $this->mockTransport
            ->method('isConnected')
            ->willReturn(true, false);

        $this->mockTransport
            ->method('receive')
            ->willReturn($messages);

        $this->mockTransport
            ->expects($matcher = $this->exactly(count($messages)))
            ->method('send')
            ->with($this->callback(function ($message) use ($messages, $matcher) {
                $this->assertEquals($messages[$matcher->numberOfInvocations() - 1], $message);

                return true;
            }));

        $this->mockTransport
            ->expects($this->once())
            ->method('close');

        $this->mcpProtocol->connect();
    }

    /**
     * Test that connect method ignores null messages without sending
     */
    public function test_connect_ignores_null_messages(): void
    {
        $messages = [null, 'validMessage', null];

        $this->mockTransport
            ->expects($this->once())
            ->method('start');

        $this->mockTransport
            ->method('isConnected')
            ->willReturn(true, false);

        $this->mockTransport
            ->method('receive')
            ->willReturn($messages);

        $this->mockTransport
            ->expects($this->once())
            ->method('send')
            ->with('validMessage');

        $this->mockTransport
            ->expects($this->once())
            ->method('close');

        $this->mcpProtocol->connect();
    }

    /**
     * Test that connect sends messages using the transport's send method
     */
    public function test_connect_sends_messages(): void
    {
        $messages = ['test_message'];

        $this->mockTransport
            ->expects($this->once())
            ->method('start');

        $this->mockTransport
            ->method('isConnected')
            ->willReturn(true, false);

        $this->mockTransport
            ->method('receive')
            ->willReturn($messages);

        $this->mockTransport
            ->expects($this->once())
            ->method('send')
            ->with($messages[0]);

        $this->mockTransport
            ->expects($this->once())
            ->method('close');

        $this->mcpProtocol->connect();
    }
}
