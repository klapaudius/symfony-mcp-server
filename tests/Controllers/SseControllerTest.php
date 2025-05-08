<?php

namespace KLP\KlpMcpServer\Tests\Controllers;

use KLP\KlpMcpServer\Controllers\SseController;
use KLP\KlpMcpServer\Server\MCPServerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseControllerTest extends TestCase
{
    private SseController $controller;
    private MCPServerInterface $mockServer;

    protected function setUp(): void
    {
        $this->mockServer = $this->createMock(MCPServerInterface::class);
        $this->controller = new SseController($this->mockServer);
    }

    public function testHandleReturnsStreamedResponse(): void
    {
        $request = $this->createMock(Request::class);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $headers = $response->headers;

        $this->assertEquals('text/event-stream', $headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $headers->get('Cache-Control'));
        $this->assertEquals('no', $headers->get('X-Accel-Buffering'));
    }

    public function testHandleExecutesServerConnect(): void
    {
        $this->mockServer
            ->expects($this->once())
            ->method('connect');

        $request = $this->createMock(Request::class);

        $response = $this->controller->handle($request);

        // Ensure response stream executes the connect callback
        ob_start();
        $response->sendContent();
        ob_end_clean();
    }
}
