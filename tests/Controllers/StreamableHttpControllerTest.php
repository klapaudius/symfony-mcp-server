<?php

namespace KLP\KlpMcpServer\Tests\Controllers;

use KLP\KlpMcpServer\Controllers\StreamableHttpController;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Server\MCPServerInterface;
use KLP\KlpMcpServer\Transports\Exception\StreamableHttpTransportException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Small]
class StreamableHttpControllerTest extends TestCase
{
    private MCPServerInterface|MockObject $mockServer;

    private LoggerInterface|MockObject $mockLogger;

    private StreamableHttpController $controller;

    protected function setUp(): void
    {
        $this->mockServer = $this->createMock(MCPServerInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->controller = new StreamableHttpController($this->mockServer, $this->mockLogger);
    }

    public function test_handle_with_get_request(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_GET);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertEquals(
            [
                'jsonrpc' => 2.0,
                'error' => 'This endpoint does not support GET requests yet.',
            ],
            json_decode($response->getContent(), true)
        );
    }

    public function test_handle_with_post_request(): void
    {
        // Create a POST request with some content
        $clientId = 'test-client-id';
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];

        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            json_encode($message)
        );
        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('mcp-session-id', $clientId);

        // Set up server mock expectations for postHandle
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        $this->mockServer->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message);

        // Call handle() which should internally call postHandle()
        $response = $this->controller->handle($request);

        // Verify the response is what we expect from postHandle()
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));

        // Execute the streamed response to trigger the callback
        ob_start();
        $response->sendContent();
        ob_end_clean();
    }

    public function test_gethandle(): void
    {
        $response = $this->controller->gethandle();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertEquals(
            [
                'jsonrpc' => 2.0,
                'error' => 'This endpoint does not support GET requests yet.',
            ],
            json_decode($response->getContent(), true)
        );
    }

    public function test_post_handle_with_single_message(): void
    {
        $clientId = 'test-client-id';
        // The controller checks for 'jsonrpc' key to determine if it's a single message
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];

        // Create request with headers and content
        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            json_encode($message)
        );
        $request->headers->set('mcp-session-id', $clientId);

        // Set up server mock expectations
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        $this->mockServer->expects($this->never())
            ->method('getClientId');

        // We can't use with() here because the actual message might be decoded differently
        $this->mockServer->expects($this->once())
            ->method('requestMessage');

        // Set up logger mock expectations - we can't be too specific about the parameters
        $this->mockLogger->expects($this->atLeastOnce())
            ->method('debug');

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));

        // Execute the streamed response to trigger the callback
        ob_start();
        $response->sendContent();
        ob_end_clean();
    }

    public function test_post_handle_with_multiple_messages_on_protocol_second_version(): void
    {
        $clientId = 'test-client-id';
        $messages = [
            ['jsonrpc' => '2.0', 'method' => 'test1', 'params' => [], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'test2', 'params' => [], 'id' => 2],
        ];

        // Create request with headers and content
        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            json_encode($messages)
        );
        $request->headers->set('mcp-session-id', $clientId);
        $request->headers->set('mcp-protocol-version', MCPProtocolInterface::PROTOCOL_SECOND_VERSION);

        // Set up server mock expectations
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_SECOND_VERSION);

        $this->mockServer->expects($this->never())
            ->method('getClientId');

        $invocations = [
            ['test-client-id', $messages[0]],
            ['test-client-id', $messages[1]],
        ];
        $this->mockServer->expects($matcher = $this->exactly(count($invocations)))
            ->method('requestMessage')
            ->with($this->callback(function (...$args) use ($invocations, $matcher) {
                $this->assertEquals($args, $invocations[$matcher->numberOfInvocations() - 1]);

                return true;
            }));

        $this->mockServer->expects($this->never())
            ->method('connect');

        // Set up logger mock expectations
        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Received message from clientId:'.$clientId), ['message' => $messages]);

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $headers = $response->headers;
        $this->assertEquals('text/event-stream', $headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $headers->get('Cache-Control'));
        $this->assertEquals('no', $headers->get('X-Accel-Buffering'));

        // Test that the streamed response executes the connect callback
        ob_start();
        $response->sendContent();
        ob_end_clean();
    }

    public function test_post_handle_with_multiple_messages_on_protocol_third_version(): void
    {
        $clientId = 'test-client-id';
        $messages = [
            ['jsonrpc' => '2.0', 'method' => 'test1', 'params' => [], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'test2', 'params' => [], 'id' => 2],
        ];

        // Create request with headers and content
        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            json_encode($messages)
        );
        $request->headers->set('mcp-session-id', $clientId);
        $request->headers->set('mcp-protocol-version', MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        // Set up server mock expectations
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        $this->mockServer->expects($this->never())
            ->method('getClientId');

        // For protocol version 3, batch requests should return an error
        $response = $this->controller->postHandle($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $responseData['jsonrpc']);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals(-32600, $responseData['error']['code']);
        $this->assertStringContainsString('Batch requests are not supported', $responseData['error']['message']);
    }

    public function test_post_handle_with_fallback_client_id(): void
    {
        $clientId = 'fallback-client-id';
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];

        // Create request without mcp-session-id header
        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            json_encode($message)
        );

        // Set up server mock expectations
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        $this->mockServer->expects($this->once())
            ->method('getClientId')
            ->willReturn($clientId);

        $this->mockServer->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message);

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));

        // Execute the streamed response to trigger the callback
        ob_start();
        $response->sendContent();
        ob_end_clean();
    }

    public function test_post_handle_with_json_parse_error(): void
    {
        // Create request with invalid JSON
        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            '{invalid-json}'
        );

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(
            ['jsonrpc' => 2.0, 'error' => ['code' => -32700, 'message' => 'Parse error']],
            json_decode($response->getContent(), true)
        );
    }

    public function test_post_handle_with_general_exception(): void
    {
        $clientId = 'test-client-id';
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];

        // Create request
        $request = new Request(
            [], // query parameters
            [], // request parameters
            [], // attributes
            [], // cookies
            [], // files
            [], // server parameters
            json_encode($message)
        );
        $request->headers->set('mcp-session-id', $clientId);

        // Set up server mock to throw an exception
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_THIRD_VERSION);

        $this->mockServer->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message)
            ->willThrowException(new StreamableHttpTransportException('Test exception'));

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        // The response is still a StreamedResponse because exceptions in callbacks are not caught
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));

        // The exception will be thrown when the callback is executed
        $this->expectException(StreamableHttpTransportException::class);
        $this->expectExceptionMessage('Test exception');

        try {
            ob_start();
            $response->sendContent();
        } finally {
            // Always clean up the output buffer, even if an exception is thrown
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }
}
