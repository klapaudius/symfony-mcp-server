<?php

namespace KLP\KlpMcpServer\Tests\Protocol;

use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\MCPProtocol;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\SseTransportInterface;
use KLP\KlpMcpServer\Transports\StreamableHttpTransportInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Small]
class MCPProtocolTest extends TestCase
{
    private TransportFactoryInterface|MockObject $mockTransportFactory;

    private SseTransportInterface|MockObject $mockTransport;

    private MCPProtocol $mcpProtocol;

    private string $exampleClientId = 'test_client_id';

    protected function setUp(): void
    {
        $this->mockTransportFactory = $this->createMock(TransportFactoryInterface::class);
        $this->mockTransport = $this->createMock(SseTransportInterface::class);
        $this->mockTransportFactory->method('create')->willReturn($this->mockTransport);
        $this->mockTransport
            ->method('getClientId')
            ->willReturn($this->exampleClientId);
        $this->mcpProtocol = new MCPProtocol($this->mockTransportFactory);
        $this->mcpProtocol->setProtocolVersion(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);
    }

    /**
     * Test that initTransport initializes transport when it is null
     */
    public function test_init_transport_initializes_transport(): void
    {
        $mcpProtocol = new MCPProtocol($this->mockTransportFactory);
        $this->mockTransportFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->equalTo(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP))
            ->willReturn($this->mockTransport);

        $this->mockTransport
            ->expects($this->once())
            ->method('onMessage');

        $mcpProtocol->setProtocolVersion(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->assertEquals($this->exampleClientId, $mcpProtocol->getClientId());
    }

    /**
     * Test that initTransport sets connected to true for StreamableHttpTransport
     */
    public function test_init_transport_sets_connected_for_streamable_http(): void
    {
        $mockTransportFactory = $this->createMock(TransportFactoryInterface::class);
        $mcpProtocol = new MCPProtocol($mockTransportFactory);
        $streamableHttpTransport = $this->createMock(StreamableHttpTransportInterface::class);
        $streamableHttpTransport
            ->method('getClientId')
            ->willReturn($this->exampleClientId);

        $mockTransportFactory
            ->method('create')
            ->willReturn($streamableHttpTransport);

        $streamableHttpTransport
            ->expects($this->once())
            ->method('setConnected')
            ->with(true);

        $streamableHttpTransport
            ->expects($this->once())
            ->method('onMessage');

        $mcpProtocol->setProtocolVersion(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->assertEquals($this->exampleClientId, $mcpProtocol->getClientId());
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

        $this->mcpProtocol->connect(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);
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

        $this->mcpProtocol->connect(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);
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

        $this->mcpProtocol->connect(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);
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

        $this->mcpProtocol->connect(MCPProtocol::PROTOCOL_VERSION_STREAMABE_HTTP);
    }

    /**
     * Test that handleMessage handles invalid JSON-RPC messages by sending an error response
     */
    public function test_handle_message_handles_invalid_jsonrpc(): void
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
    public function test_handle_message_handles_valid_request(): void
    {
        $clientId = 'client_1';
        $validRequestMessage = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'test.method', 'params' => ['param1' => 'value1']];

        $mockHandler = $this->createMock(\KLP\KlpMcpServer\Protocol\Handlers\RequestHandler::class);
        $mockHandler->method('isHandle')->with('test.method')->willReturn(true);
        $mockHandler->method('execute')->with('test.method', $clientId, 1, ['param1' => 'value1'])->willReturn(['response' => 'ok']);
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
    public function test_handle_message_handles_valid_notification(): void
    {
        $clientId = 'client_1';
        $validNotificationMessage = ['jsonrpc' => '2.0', 'method' => 'notify.method', 'params' => ['param1' => 'value1']];

        $mockHandler = $this->createMock(NotificationHandler::class);
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
    public function test_handle_message_handles_unknown_method(): void
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
    public function test_handle_message_handles_unknown_notification(): void
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

    public function test_handle_message_handles_no_method(): void
    {
        $clientId = 'client_1';
        $noMethodMessage = ['jsonrpc' => '2.0', 'id' => 1];

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (...$args) use ($clientId) {
                $data = $args[1];
                $this->assertEquals($clientId, $args[0]);
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertEquals(1, $data['id']);
                $this->assertEquals(-32600, $data['error']['code']);
                $this->assertEquals('Invalid Request: Message format not recognized', $data['error']['message']);

                return true;
            }));

        $this->mcpProtocol->handleMessage($clientId, $noMethodMessage);
    }

    public function test_handle_message_handles_ping_request(): void
    {
        $clientId = 'client_1';
        // response to a ping request from client
        $noMethodMessage = ['jsonrpc' => '2.0', 'id' => 1, 'result' => []];

        $this->mockTransport
            ->expects($this->never())
            ->method('pushMessage');

        $this->mcpProtocol->handleMessage($clientId, $noMethodMessage);
    }

    public function test_handle_message_handles_invalid_params(): void
    {
        $clientId = 'client_1';
        $invalidParamsMessage = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'test.method', 'params' => ['param1' => 'invalid']];

        $mockHandler = $this->createMock(\KLP\KlpMcpServer\Protocol\Handlers\RequestHandler::class);
        $mockHandler->method('isHandle')->with('test.method')->willReturn(true);
        $mockHandler->method('execute')->with('test.method', $clientId, 1, ['param1' => 'invalid'])
            ->willThrowException(new ToolParamsValidatorException('An error occurred.', ['Invalid params param1']));

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (...$args) use ($clientId) {
                $data = $args[1];
                $this->assertEquals($clientId, $args[0]);
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertEquals(1, $data['id']);
                $this->assertEquals(-32602, $data['error']['code']);

                $this->assertEquals('An error occurred. Invalid params param1', $data['error']['message']);

                return true;
            }));

        $this->mcpProtocol->registerRequestHandler($mockHandler);
        $this->mcpProtocol->handleMessage($clientId, $invalidParamsMessage);
    }

    public function test_handle_message_handles_handler_throw_exception(): void
    {
        // Arrange
        $clientId = 'client_1';
        $invalidParamsMessage = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'test.method', 'params' => ['param1' => 'invalid']];
        $mockHandler = $this->createMock(\KLP\KlpMcpServer\Protocol\Handlers\RequestHandler::class);
        $mockHandler->method('isHandle')->with('test.method')->willReturn(true);
        $mockHandler->method('execute')->with('test.method', $clientId, 1, ['param1' => 'invalid'])
            ->willThrowException(new \Exception('An error occurred.'));

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (...$args) use ($clientId) {
                $data = $args[1];
                $this->assertEquals($clientId, $args[0]);
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertEquals(1, $data['id']);
                $this->assertEquals(-32603, $data['error']['code']);
                $this->assertEquals('An error occurred.', $data['error']['message']);

                return true;
            }));
        $this->mcpProtocol->registerRequestHandler($mockHandler);

        // Act
        $this->mcpProtocol->handleMessage($clientId, $invalidParamsMessage);
    }

    public function test_handle_message_handles_notification_handler_throw_exception(): void
    {
        $clientId = 'client_1';
        $validNotificationMessage = ['jsonrpc' => '2.0', 'method' => 'notify.method', 'params' => ['param1' => 'value1']];

        $mockHandler = $this->createMock(NotificationHandler::class);
        $mockHandler->method('isHandle')->with('notify.method')->willReturn(true);
        $mockHandler->expects($this->once())->method('execute')
            ->with(['param1' => 'value1'])
            ->willThrowException(new \Exception('An error occurred.'));
        $this->mcpProtocol->registerNotificationHandler($mockHandler);

        $this->mockTransport
            ->expects($this->once())
            ->method('pushMessage')
            ->with($this->callback(function (...$args) use ($clientId) {
                $data = $args[1];
                $this->assertEquals($clientId, $args[0]);
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertEquals(-32603, $data['error']['code']);
                $this->assertEquals('An error occurred.', $data['error']['message']);

                return true;
            }));

        $this->mcpProtocol->handleMessage($clientId, $validNotificationMessage);
    }

    /**
     * Test that requestMessage invokes processMessage on the transport
     */
    public function test_request_message_invokes_transport_process_message(): void
    {
        $clientId = 'test_client';
        $message = ['key' => 'value'];

        $this->mockTransport
            ->expects($this->once())
            ->method('processMessage')
            ->with($this->equalTo($clientId), $this->equalTo($message));

        $this->mcpProtocol->requestMessage($clientId, $message);
    }

    /**
     * Test getClientId method returns a valid client ID from the transport layer
     */
    public function test_get_client_id_returns_valid_client_id(): void
    {
        $clientId = $this->mcpProtocol->getClientId();

        $this->assertEquals($this->exampleClientId, $clientId);
    }

    /**
     * Test that getResponseResult returns decoded messages from the transport
     */
    public function test_get_response_result_returns_decoded_messages(): void
    {
        $messages = ['{"key":"value"}', '{"anotherKey":"anotherValue"}'];
        $decodedMessages = [json_decode($messages[0]), json_decode($messages[1])];

        $this->mockTransport
            ->method('receive')
            ->willReturn($messages);

        $result = $this->mcpProtocol->getResponseResult($this->exampleClientId);

        $this->assertEquals($decodedMessages, $result);
    }

    /**
     * Test that getResponseResult skips null messages
     */
    public function test_get_response_result_skips_null_messages(): void
    {
        $messages = ['{"key":"value"}', null, '{"anotherKey":"anotherValue"}'];
        $decodedMessages = [json_decode($messages[0]), json_decode($messages[2])];

        $this->mockTransport
            ->method('receive')
            ->willReturn($messages);

        $result = $this->mcpProtocol->getResponseResult($this->exampleClientId);

        $this->assertEquals($decodedMessages, $result);
    }
}
