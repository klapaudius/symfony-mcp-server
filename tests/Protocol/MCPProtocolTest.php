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

    /**
     * Test that handleMessage handles invalid JSON-RPC messages by sending an error response
     */
    public function test_handleMessage_handles_invalid_jsonrpc(): void
    {
        $clientId = 'client_1';
        $invalidMessage = ['id' => 1, 'method' => 'example.method'];

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function (array $response) {
                    $this->assertEquals('2.0', $response['jsonrpc']);
                    $this->assertEquals(-32600, $response['error']['code']);
                    $this->assertEquals('Invalid Request: Not a valid JSON-RPC 2.0 message', $response['error']['message']);
                    return true;
                })
            );

        $this->mcpProtocol->handleMessage($clientId, $invalidMessage);
    }

    /**
     * Test that handleMessage processes valid request messages and sends results using the transport
     */
    public function test_handleMessage_handles_valid_request(): void
    {
        $clientId = 'client_1';
        $validRequestMessage = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'test.method', 'params' => ['param1' => 'value1']];

        $mockHandler = $this->createMock(\KLP\KlpMcpServer\Protocol\Handlers\RequestHandler::class);
        $mockHandler->method('isHandle')->with('test.method')->willReturn(true);
        $mockHandler->method('execute')->with('test.method', ['param1' => 'value1'])->willReturn(['response' => 'ok']);
        $this->mcpProtocol->registerRequestHandler($mockHandler);

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with(
                $clientId,
                $this->callback(function (array $response) {
                    $this->assertEquals('2.0', $response['jsonrpc']);
                    $this->assertEquals(1, $response['id']);
                    $this->assertEquals(['response' => 'ok'], $response['result']);
                    return true;
                })
            );

        $this->mcpProtocol->handleMessage($clientId, $validRequestMessage);
    }

    /**
     * Test that handleMessage processes valid notification messages without sending a response
     */
    public function test_handleMessage_handles_valid_notification(): void
    {
        $clientId = 'client_1';
        $validNotificationMessage = ['jsonrpc' => '2.0', 'method' => 'notify.method', 'params' => ['param1' => 'value1']];

        $mockHandler = $this->createMock(\KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler::class);
        $mockHandler->method('isHandle')->with('notify.method')->willReturn(true);
        $mockHandler->expects($this->once())->method('execute')->with(['param1' => 'value1']);
        $this->mcpProtocol->registerNotificationHandler($mockHandler);

        $this->mockTransport
            ->expects($this->never())
            ->method('pushMessage');

        $this->mcpProtocol->handleMessage($clientId, $validNotificationMessage);
    }

    /**
     * Test that handleMessage responds with an error for unknown methods in requests
     */
    public function test_handleMessage_handles_unknown_method(): void
    {
        $clientId = 'client_1';
        $unknownRequestMessage = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'unknown.method'];

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (...$args) use ($clientId) {
                $data = $args[1];
                $this->assertEquals($clientId, $args[0]);
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertEquals(1, $data['id']);
                $this->assertEquals(-32601, $data['error']['code']);
                $this->assertEquals('Method not found: unknown.method', $data['error']['message']);
                return true;
            }));

        $this->mcpProtocol->handleMessage($clientId, $unknownRequestMessage);
    }

    /**
     * Test that handleMessage responds with an error for unknown notification in requests
     */
    public function test_handleMessage_handles_unknown_notification(): void
    {
        $clientId = 'client_1';
        $unknownNotificationMessage = ['jsonrpc' => '2.0', 'method' => 'unknown.notify'];

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (...$args) use ($clientId) {
                $data = $args[1];
                $this->assertEquals($clientId, $args[0]);
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertEquals(-32601, $data['error']['code']);
                $this->assertEquals('Method not found: unknown.notify', $data['error']['message']);
                return true;
            }));

        $this->mcpProtocol->handleMessage($clientId, $unknownNotificationMessage);
    }
}
