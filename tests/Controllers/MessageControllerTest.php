<?php

namespace KLP\KlpMcpServer\Tests\Controllers;

use KLP\KlpMcpServer\Controllers\MessageController;
use KLP\KlpMcpServer\Server\MCPServerInterface;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[Small]
class MessageControllerTest extends TestCase
{
    private MCPServerInterface|MockObject $mockServer;

    private LoggerInterface|MockObject|null $mockLogger;

    private MessageController $controller;

    protected function setUp(): void
    {
        $this->mockServer = $this->createMock(MCPServerInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->controller = new MessageController($this->mockServer, $this->mockLogger);
    }

    public function test_handle_success_with_session_id_from_request_body(): void
    {
        $sessionId = '12345';
        $messageData = ['key' => 'value'];

        $request = new Request([], ['sessionId' => $sessionId], [], [], [], [], json_encode($messageData));

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Received message for clientId:'.$sessionId), ['message' => $messageData]);

        $this->mockServer
            ->expects($this->once())
            ->method('requestMessage')
            ->with($sessionId, $messageData);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(['success' => true], json_decode($response->getContent(), true));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_success_with_session_id_from_query(): void
    {
        $sessionId = '67890';
        $messageData = ['key' => 'value'];

        $request = new Request(['sessionId' => $sessionId], [], [], [], [], [], json_encode($messageData));

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Received message for clientId:'.$sessionId), ['message' => $messageData]);

        $this->mockServer
            ->expects($this->once())
            ->method('requestMessage')
            ->with($sessionId, $messageData);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(['success' => true], json_decode($response->getContent(), true));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_throws_json_decode_error(): void
    {
        $request = new Request([], [], [], [], [], [], '{invalid-json}');

        $this->mockLogger->expects($this->never())->method('debug');
        $this->mockServer->expects($this->never())->method('requestMessage');

        $this->expectException(\JsonException::class);

        $this->controller->handle($request);
    }

    public function test_handle_without_session_id(): void
    {
        $messageData = ['key' => 'value'];
        $request = new Request([], [], [], [], [], [], json_encode($messageData));

        $this->mockLogger
            ->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Received message for clientId:'), ['message' => $messageData]);
        $this->mockServer->expects($this->never())->method('requestMessage');

        $this->expectException(\TypeError::class);

        $this->controller->handle($request);
    }
}
