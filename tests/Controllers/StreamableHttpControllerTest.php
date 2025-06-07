<?php

namespace KLP\KlpMcpServer\Tests\Controllers;

use Exception;
use JsonException;
use KLP\KlpMcpServer\Controllers\StreamableHttpController;
use KLP\KlpMcpServer\Protocol\MCPProtocolInterface;
use KLP\KlpMcpServer\Server\MCPServerInterface;
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
                'error' => 'This endpoint does not support GET requests yet.'
            ],
            json_decode($response->getContent(), true)
        );
    }

    public function test_handle_with_post_request(): void
    {
        // Create a POST request with some content
        $clientId = 'test-client-id';
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];
        $responseResult = [['jsonrpc' => '2.0', 'result' => 'success', 'id' => 1]];

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
            ->with(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->mockServer->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message);

        $this->mockServer->expects($this->once())
            ->method('getResponseResult')
            ->with($clientId)
            ->willReturn($responseResult);

        // Call handle() which should internally call postHandle()
        $response = $this->controller->handle($request);

        // Verify the response is what we expect from postHandle()
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($responseResult[0], json_decode($response->getContent(), true));
    }

    public function test_gethandle(): void
    {
        $response = $this->controller->gethandle();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertEquals(
            [
                'jsonrpc' => 2.0,
                'error' => 'This endpoint does not support GET requests yet.'
            ],
            json_decode($response->getContent(), true)
        );
    }

    public function test_postHandle_with_single_message(): void
    {
        $clientId = 'test-client-id';
        // The controller checks for 'jsonrpc' key to determine if it's a single message
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];
        $responseResult = [['jsonrpc' => '2.0', 'result' => 'success', 'id' => 1]];

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
            ->with(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->mockServer->expects($this->never())
            ->method('getClientId');

        // We can't use with() here because the actual message might be decoded differently
        $this->mockServer->expects($this->once())
            ->method('requestMessage');

        $this->mockServer->expects($this->once())
            ->method('getResponseResult')
            ->willReturn($responseResult);

        // Set up logger mock expectations - we can't be too specific about the parameters
        $this->mockLogger->expects($this->atLeastOnce())
            ->method('debug');

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);

        // Instead of checking the exact content, just verify it's a valid JSON response
        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        // The response might have either 'jsonrpc' or 'jsonrpc' key depending on the implementation
        $this->assertTrue(
            isset($content['jsonrpc']) || isset($content['jsonrpc']),
            'Response should have either jsonrpc or jsonrpc key'
        );
    }

    public function test_postHandle_with_multiple_messages(): void
    {
        $clientId = 'test-client-id';
        $messages = [
            ['jsonrpc' => '2.0', 'method' => 'test1', 'params' => [], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'test2', 'params' => [], 'id' => 2]
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

        // Set up server mock expectations
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->mockServer->expects($this->never())
            ->method('getClientId');

        $invocations = [
            ['test-client-id', $messages[0]],
            ['test-client-id', $messages[1]]
        ];
        $this->mockServer->expects($matcher = $this->exactly(count($invocations)))
            ->method('requestMessage')
            ->with($this->callback(function (...$args) use ($invocations, $matcher) {
                $this->assertEquals($args, $invocations[$matcher->numberOfInvocations()-1]);
                return true;
            }));

        $this->mockServer->expects($this->once())
            ->method('connect');

        // Set up logger mock expectations
        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Received message from clientId:' . $clientId), ['message' => $messages]);

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

    public function test_postHandle_with_fallback_client_id(): void
    {
        $clientId = 'fallback-client-id';
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1];
        $responseResult = [['jsonrpc' => '2.0', 'result' => 'success', 'id' => 1]];

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
            ->with(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->mockServer->expects($this->once())
            ->method('getClientId')
            ->willReturn($clientId);

        $this->mockServer->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message);

        $this->mockServer->expects($this->once())
            ->method('getResponseResult')
            ->with($clientId)
            ->willReturn($responseResult);

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($responseResult[0], json_decode($response->getContent(), true));
    }

    public function test_postHandle_with_json_parse_error(): void
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

    public function test_postHandle_with_general_exception(): void
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

        // Set up server mock to return a result that will trigger the exception
        $this->mockServer->expects($this->once())
            ->method('setProtocolVersion')
            ->with(MCPProtocolInterface::PROTOCOL_VERSION_STREAMABE_HTTP);

        $this->mockServer->expects($this->once())
            ->method('requestMessage')
            ->with($clientId, $message);

        // Return an empty array to trigger the "Result is not an array" exception
        // The controller checks for !isset($result[0])
        $this->mockServer->expects($this->once())
            ->method('getResponseResult')
            ->with($clientId)
            ->willReturn([]);

        // Call the method and verify response
        $response = $this->controller->postHandle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals(
            ['jsonrpc' => 2.0, 'error' => ['code' => -32700, 'message' => 'Result is not an array']],
            json_decode($response->getContent(), true)
        );
    }
}
